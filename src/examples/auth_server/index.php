<?php
/**
 * auth 授权服务器测试代码
 */
$auth = [
    // 每隔五秒查询一次授权服务器
    'ttl'            => 5,
    //身份，授权成功后返回给客户端
    'identity'       => 'authServer',
    //授权链接可以忽略,授权成功后返回给客户端
    'identity_url'   => 'http://w.auth.com/api/auth',
    'authorizations' => [
        [
            'permissions' => ['subscribe', 'publish'],
			//授权的话题
            'topic'       => 'nsq_common',
			//授权的频道
            'channels'    => [
                '.*'
            ]
        ],
		[
            'permissions' => ['subscribe', 'publish'],
			//授权的话题
            'topic'       => 'nsq_test',
			//授权的频道
            'channels'    => [
                '.*'
            ]
        ]
    ]
];
//throw new Exception('');
echo json_encode($auth, JSON_UNESCAPED_UNICODE);
