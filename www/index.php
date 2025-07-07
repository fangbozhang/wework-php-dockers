<?php
gc_enable();
$startMemUsed = memory_get_usage();
function cal_mem()
{
        global $startMemUsed;
        echo "memory,", ceil((memory_get_usage() - $startMemUsed) / 1024), 'KB', PHP_EOL;
}
echo "start mypid=", getmypid(), PHP_EOL;
sleep(1);
$sdk = new WxworkFinanceSdk("ww488d77c72705150f", "xXH1rFGGw-zedjkAkC1b1Ze9mvSJUhf5dytnyPhxFXY");

$privateKey = <<<EOF
-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEAgFtIczgQFwRwozGGjqekIzKzH6oKCe0yMWfZMD1GUSLazTRq
TSVe/VSU49YOLApz3WQ4ca2xVf7loejp+66eIaZE5wDi4vALfakvyZ6pg7Hq+TTH
cgQpbEm+k87enlBzLkVVKX9EDdZ1JT1H+hx6YpUopOPQS6Mqbil2IqlRPiAqvFw6
CQTMUjJtJYoivuXrzsndLa/b2xav3Ye03KJR0/hzV//bc2XCLgRQvanGl/6Z1+bx
9ovs/MJ6nQ7EK7SDRZpQCpTxoQ3Gqj8rVs07suGPOkKFDa/PMM10X27TsEUclxRn
SeRQLDbGHh2eSgO4H1vkopojrcN8qEwXqQ7ynwIDAQABAoIBACMPS/nUzWhMGSwq
QfPDTK0kkxLKElXlyTj/ga6Qfh15ZMR6VbLey1Rs/wJAnLxg2ocVcelzJSY1KqoQ
AaFyb9UHInjqoA6WvLzFMr1irjC/r0wEo5m8E0h12C1taxdZKCzyWTGthnw1IOhc
FcX2c2NsFJ79bw7J8bQHdTJAh2VtYXi2sQqVlAp0fJvfs9ZQ1e63YKrBUBYJeiKQ
97BlzzKhIIcZWuoP3zk+k5jMcclebq6oPOAaFkDiPPMCB6n5Mo0QrMTdcWUDKpov
DFHJBi6i1x4PO+F4ZtG2Tv8+Pc0Djbk6mSlrqLakWKdduR+ws0/mZjYdawRzi2Nx
UF1GXVkCgYEAgpfknY8WTX2OiD2h4P2u0nYGVItoc+O7KaEUSq0DOhac94SJuGvw
3tAap2YBehC3rPWENWbWeq8rAudxysKVqVQWSJsjmN+DmvVP9MWVDuZvqyQqBh0n
tf6myjn4ym20SZ8UyG8MMeYmxQ5hNsm7+FnNNqqeJAIwnyf7xDeFF00CgYEA+52F
ljG7YApVHBZ7EuP4lhmFWRiMJxerYMRoZkMp43a01VT8dvvN74Vbf624MXH627Uq
3qZY/HqEm23zqBTcmihijZWJikJW9v0drw32CK3w1+dFA+17RDJvate/J+reH8R/
F8wPRlrP7pa+wcbQkMjkwUR8LPLbA/TbGrtrs5sCgYBOMz6GysQEKwdKtf1ViRNC
m8I2pjQqEVhmGTrZbLjd8+SSox8E/D4EboFHdGG2AoS6YVqFz8rnNDWBS65sSBDu
kJe3ao7qYA2ioPr8C8SyY3LC/KjdeF/rL04ZEpXUQdUPsN71FuoqhzL4FSBJeovA
r6We8pQ348fRxlOQr95WkQKBgQCaCTgz16RHSmwKMvULfoa7lUoeXjnG4OWo6vSi
zjFBsHVKOKoKSWMsZC68vmQJ2SZjBMkG3z2Q64xs/uXwmzzmHx0eYlJ+Uticgh5/
AYQCkkHkWw/UNLmG6X1uIkBDNrTfK9NGhUVAo+2xuZV0WbtrN6FbdAq1FcPg6zCL
b/uiSwKBgDYCJ4lf8LH0eWpnEuhd8TgBdGylRlu/LUiZRZYRbN73cuaqsiV+vlOZ
td7R5hmbt1n+zUh/IdEHCAUn4H6ZEOgSF1maztsgF0UyVOlhRwm+OUDuBlkFrqOk
+ufUYscSqTjA5/X/7wWelLMtzs1JXxZFam1jYMTUtkerVPiU//J1
-----END RSA PRIVATE KEY-----
EOF;
$seq = 0;
$str = str_repeat('X', 1024 * 1024);

do{

        echo "sync seq={$seq} ==>", cal_mem(), PHP_EOL;
        gc_mem_caches();
        gc_collect_cycles();
        $wxChat = $sdk->getChatData($seq, 100);
        $chats = json_decode($wxChat, true);
        $chatRows = $chats['chatdata'];
        foreach ($chatRows as $val) {
                $decryptRandKey = null;
                $decryptData = openssl_private_decrypt(base64_decode($val['encrypt_random_key']), $decryptRandKey, $privateKey, OPENSSL_PKCS1_PADDING);
                $decryptChatRawContent = $sdk->decryptData($decryptRandKey, $val['encrypt_chat_msg']);
                var_dump($decryptChatRawContent);
                $j2 = json_decode($decryptChatRawContent, true);
            
                $msgType = $j2['msgtype'];
                     if (in_array($msgType, ['image', 'video'])) {
                        try {
                                $sdk->downloadMedia($j2[$msgType]['sdkfileid'], "/tmp/download/{$j2[$msgType]['md5sum']}");
                        }catch(\Exception $e) {
                                var_dump($e);
                                var_dump($e->getMessage(), $e->getCode());
                                sleep(1);
                        }
                }
                unset($decryptRandKey);
        }
        echo "loop done ===>", cal_mem();
        unset($chatRows, $wxChat, $chats);
        gc_collect_cycles();gc_collect_cycles();
        $seq = $val['seq'];
}while(true);
cal_mem();