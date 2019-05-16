#nsq_swoole_client
基于swoole实现的NSQ客户端。支持sub、pub、mPub、auth。
lumen 下的使用实例（laravel同理）：[github](https://github.com/tttlkkkl/swoole-nsq)。
```
composer require "tttlkkkl/php_nsq_client:~1.1.5" -vvv
```

- 消息去重、重新排队的机机制、日志等都可以通过实现已定义的接口去中心实现。一般情况下只需要实现消息处理方法即可。
- 默认实现的消费客户端构造方法参数 `$finishAuto` 为 `true` 时，如果 `handle` 方法返回`false`或者抛出任何异常都将自动根据`requeue` 对象重新排队。
- 如果已对`message`对象调用了`finish()`或者 `requeue()`方法并且失败时,走 `$finishAuto` 为 `true` 时的逻辑。
- `$finishAuto` 为 `false` 时,必须手动调用`finish()`或者 `requeue()`。
- `helper` 目录中封装了常用的`http` `pub`和`mpub`。
- 基于swoole Process 实现进程池。进程数量动态变更。
- 设置最小进程数量等于最大进程数量时进程数量固定，这是建议的做法。


### 示例：
```php
    $host = '127.0.0.1:4150';
    $topic = $channel = 'test';
    // 重复排队10次，每次50秒延时下发
    $reQueue = new \NsqClient\lib\requeue\Requeue(10, 50);
    $client = new Client(
        $topic,
        $channel,
        '',
        function (\NsqClient\lib\message\Message &$message) {
            echo "收到消息:{$message->getId()}\n";
            $message->finish();
        },
        true,
        null,
        $reQueue,
        [
            'heartbeat_interval' => 1000//1秒的心跳间隔
        ]
    );
    // 最小任务进程数
    $min_woker_num = 2;
    // 最大任务进程数
    $max_woker_num = 10;
    // 空闲30秒后退出任务进程
    $idle_seconds = 30;
    (new NsqClient())->init($client, $host, $min_woker_num, $max_woker_num, $idle_seconds);
}
```