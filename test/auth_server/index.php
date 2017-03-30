<?php

$auth = [
    'ttl'            => 5,
    'identity'       => 'authServer',//身份
    'identity_url'   => 'http://w.auth.com/api/auth',//授权链接可以忽略
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
