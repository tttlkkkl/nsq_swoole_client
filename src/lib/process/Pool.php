<?php
/**
 * 进程池 维持最小任务进程数量并且在小于最大进程数的范围内自动伸缩
 *
 * Created by li hua.
 * User: m
 * Date: 2019/5/15
 * Time: 10:27
 */

namespace NsqClient\lib\process;

use NsqClient\lib\client\ClientInterface;
use NsqClient\lib\exception\ClientException;
use NsqClient\lib\handle\HandleInterface;
use NsqClient\lib\message\Message;
use NsqClient\lib\message\Packet;
use Swoole\Process;
use Swoole\Client as SClient;
use Closure;

class Pool
{

    /**
     * @var task 保存进程 [pid=>process]
     */
    private $workers = [];


    /**
     * @var array  [pid=>isUsed]
     */
    private $useds = [];
    /**
     * @var array [pid=>time()]
     */
    private $times = [];
    /**
     * @var int 最小进程数也是初始进程数量
     */
    private $min_woker_num = 2;

    /**
     * @var int 最大进程数
     */
    private $max_woker_num = 20;

    /**
     * 进程闲置销毁秒数
     * @var int
     */
    private $idle_seconds = 5;


    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var string
     */
    private $nsqdHost;

    /**
     * @var
     */
    private $mPid;


    /**
     * Pool constructor.
     * @param ClientInterface $Client
     * @param $nsqdHost
     * @param int $min_woker_num
     * @param int $max_woker_num
     * @param int $idle_seconds
     */
    public function __construct(ClientInterface $Client, $nsqdHost, $min_woker_num = 2, $max_woker_num = 20, $idle_seconds = 30)
    {
        $this->client = $Client;
        $this->nsqdHost = $nsqdHost;
        if ($min_woker_num < 1) {
            throw new ClientException('最小task进程数不能小少于1个');
        }
        if ($max_woker_num < $min_woker_num) {
            throw new ClientException('最大task进程数不能小少于最小进程数');
        }
        if ($idle_seconds < 1) {
            throw new ClientException('task进程空闲时间不能小于1秒');
        }
        $this->min_woker_num = $min_woker_num;
        $this->max_woker_num = $max_woker_num;
        $this->idle_seconds = $idle_seconds;
        $this->mPid = posix_getpid();
        $this->client->setRdy($max_woker_num);
    }

    public function init()
    {
        // master 进程同时也运行TCP客户端
        $pool = new Process(function (Process $masterWorker) {
            $masterWorker->name('master-' . $this->client->getTopic());
            $tcpClient = new SClient(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
            $tcpClient->on('connect', [$this->client, 'onConnect']);
            $tcpClient->on('receive', [$this->client, 'onReceive']);
            $tcpClient->on('error', [$this->client, 'onError']);
            $tcpClient->on('close', [$this->client, 'onClose']);
            $tcpClient->set([
                'package_max_length'    => 1024 * 1024 * 2,
                'open_length_check'     => true,
                'package_length_type'   => 'N',
                'package_length_offset' => 0,       //第N个字节是包长度的值
                'package_body_offset'   => 4,       //第几个字节开始计算长度
            ]);
            if (($i = strpos($this->nsqdHost, ':')) === false) {
                $port = 4151;
            } else {
                $host = substr($this->nsqdHost, 0, $i);
                $port = substr($this->nsqdHost, $i + 1);
            }
            // 创建最少的 task 进程
            for ($i = 0; $i < $this->min_woker_num; $i++) {
                $this->createTaskWorker();
            }
            $co = $tcpClient->connect($host, $port);
            if (!$co) {
                throw new ClientException('无法连接到:' . $host . ':' . $port);
            } else {
                $this->client->setHost($this->nsqdHost, 4150);
                $task = function ($worker, SClient $client) {
                    $pid = $worker->pid;
                    if (!swoole_event_isset($worker->pipe, SWOOLE_EVENT_READ)) {
                        // 监听子进程回传的消息
                        swoole_event_add($worker->pipe, function ($pipe) use ($worker, $client, $pid) {
                            $childData = $worker->read();
                            if ($childData == '@fin') {//表示任务完成
                                $this->useds[$pid] = false;
                            } else {// 否则表示回传nsq的信息
                                $client->send($childData);
                            }
                        });
                    }
                    $this->useds[$pid] = true;
                    $this->times[$pid] = time();
                    // 消息已投递无需创建新进程
                    $this->client->getLog()->debug('投递任务到 task 成功 #' . $pid);
                };
                // 设置消息处理
                $this->client->setTask(function (SClient $client, $frame) use ($masterWorker, $task) {
                    $this->client->getLog()->debug('准备投递任务' . $frame);
                    $isNeedCreate = true;
                    foreach ($this->workers as $pid => $worker) {
                        // 空闲状态则投递任务
                        if ($this->isUsed($pid) === false) {
                            $rs = $worker->write($frame);
                            // 创建子进程消息管道监听
                            if ($rs) {
                                $task($worker, $client);
                                $isNeedCreate = false;
                                break;
                            }
                        }
                    }
                    if ($isNeedCreate) {
                        if (count($this->workers) < $this->max_woker_num) {
                            $pid = $this->createTaskWorker();
                            $newWorker = $this->workers[$pid];
                            $rs = $newWorker->write($frame);
                            // 创建子进程消息管道监听
                            if ($rs) {
                                $task($newWorker, $client);
                                $this->workers[$pid] = $newWorker;
                            }
                            $this->client->getLog()->info('创建新的处理进程#' . $pid);
                        } else {
                            // 暂停接收消息
                            $this->client->getLog()->debug('已达到最大进程数' . count($this->workers) . '暂停接收消息');
                            // 已经开到最大进程数，消息重新排队10秒
                            $client->send(Packet::req((new Message(unserialize($frame), $masterWorker))->getId(), 10));
                        }
                        return;
                    }
                    $this->checkExit($masterWorker);
                });
                // 定时清除闲置的进程
                swoole_timer_tick(1000, function ($timeId) {
                    foreach ($this->workers as $pid => $worker) {
                        if ($this->isUsed($pid) == false && (time() - $this->getLastActive($pid)) > $this->idle_seconds && count($this->workers) > $this->min_woker_num) {
                            $worker->write('@exit');
                            unset($this->workers[$pid]);
                            unset($this->useds[$pid]);
                            unset($this->times[$pid]);
                        }
                        $this->checkExit($worker);
                    }
                });
            }
            $this->client->getLog()->info('启动 TCP 连接成功!');
        }, false, SOCK_DGRAM);
        $mpid = $pool->start();
        $this->client->getLog()->info('start master worker #' . $mpid);
        return $mpid;
    }

    /**
     * 是否已被使用
     *
     * @param $pid
     * @return bool|mixed
     */
    private function isUsed($pid)
    {
        return isset($this->useds[$pid]) ? $this->useds[$pid] : true;
    }

    /**
     * 上一次活动时间
     *
     * @param $pid
     * @return int|mixed
     */
    private function getLastActive($pid)
    {
        return isset($this->times[$pid]) ? $this->times[$pid] : 0;
    }

    /**
     * 如果主进程退出就退出
     *
     * @param Process $worker
     */
    private function checkExit(Process $worker)
    {
        !Process::kill($this->mPid, 0) && Process::kill($worker->pid) && $worker->exit(0);
    }

    /**
     * 创建任务进程
     */
    private function createTaskWorker()
    {
        $worker_process = new Process(function (Process $worker) {
            $worker->name('task-' . $this->client->getTopic());
            // 监听子进程管道
            swoole_event_add($worker->pipe, function ($pipe) use ($worker) {
                $data = $worker->read();
                if ($data === '@exit' && Process::kill($worker->pid)) {
                    $this->client->getLog()->debug('退出空闲子进程 #' . $worker->pid);
//                    $worker->exit(0);
                }
                $this->client->getLog()->debug('收到 task 投递任务 #' . $worker->pid);
                $message = new Message(unserialize($data), $worker);
                try {
                    $handleFun = $this->client->getHandle();
                    if ($handleFun instanceof HandleInterface) {
                        $handle = $handleFun->handle($message);
                    } elseif ($handleFun instanceof Closure) {
                        $handle = call_user_func($handleFun, $message);
                    }
                } catch (\Exception $E) {
                    $this->client->getLog()->error($E->getMessage() . "\n" . $E->getTraceAsString());
                    $handle = false;
                }
                // 消息自动完成
                if ($this->client->finishAuto == true) {
                    if ($message->isHandle()) {
                        $worker->write('@fin');
                        $this->checkExit($worker);
                        return;
                    }
                    if ($handle) {
                        $this->client->getLog()->info('消息处理完毕:' . $message->getMsg());
                        $message->finish();
                    } else {
                        $this->client->getLog()->error('消息处理出错:' . $message->getMsg());
                        // 是否满足重新排队的要求
                        if (($timeout = $this->client->Requeue->shouldRequeue($message)) !== NULL) {
                            if ($message->requeue($timeout)) {
                                $this->client->getLog()->info('消息自动重新排队:' . $message->getMsg());
                            } else {
                                $this->client->getLog()->error('消息自动排队失败:' . $message->getMsg());
                            }
                        } else {
                            $message->finish();
                            $this->client->getLog()->info('消息被丢弃:' . $message->getMsg());
                        }
                    }
                }
                $worker->write('@fin');
                $this->checkExit($worker);
            });
        }, false, SOCK_DGRAM);

        $worker_pid = $worker_process->start();
        $this->client->getLog()->info('start task worker #' . $worker_pid);
        $this->workers[$worker_pid] = $worker_process;
        $this->useds[$worker_pid] = false;
        $this->times[$worker_pid] = time();
        return $worker_pid;
    }
}