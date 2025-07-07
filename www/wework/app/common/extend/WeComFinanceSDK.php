<?php

namespace app\common\extend;

use FFI;
use Exception;

class WeComFinanceSDK {
    private $ffi;
    private $sdkPtr;

    public function __construct() {
// 使用绝对路径确保正确加载
        $soPath = realpath(config('wecom.sdk_so_path'));

        if (!$soPath || !file_exists($soPath)) {
            throw new Exception("企业微信SDK文件不存在: " . config('wecom.sdk_so_path'));
        }

// 从SDK头文件中提取的函数定义
        $ffiDef = <<<CDEF
        typedef void* WeWorkFinanceSdk_t;
        
        WeWorkFinanceSdk_t NewSdk();
        int Init(WeWorkFinanceSdk_t sdk, const char* corpid, const char* secret);
        int GetChatData(WeWorkFinanceSdk_t sdk, uint64_t seq, uint64_t limit, void** chatDataArray);
        void FreeSlice(void* slice);
        char* DecryptData(const char* private_key, const char* encrypt_random_key, const char* encrypt_chat_msg);
        void DestroySdk(WeWorkFinanceSdk_t sdk);
        CDEF;

// 创建FFI实例
        $this->ffi = FFI::cdef($ffiDef, $soPath);

// 创建SDK实例
        $this->sdkPtr = $this->ffi->NewSdk();
        if (FFI::isNull($this->sdkPtr)) {
            throw new Exception('创建企业微信SDK实例失败');
        }

// 初始化SDK
        $corpId = config('wecom.corp_id');
        $secret = config('wecom.secret');
        $ret = $this->ffi->Init($this->sdkPtr, $corpId, $secret);
        if ($ret != 0) {
            throw new Exception("SDK初始化失败，错误码: $ret");
        }
    }

    /**
     * 获取聊天记录
     */
    public function getChatData($seq, $limit) {
        $chatDataArrayPtr = $this->ffi->new('void*');
        $ret = $this->ffi->GetChatData($this->sdkPtr, $seq, $limit, FFI::addr($chatDataArrayPtr));

        if ($ret != 0) {
            throw new Exception("获取聊天数据失败，错误码: $ret");
        }

        // 解析聊天数据结构
        $chatDataArray = $this->parseChatDataArray($chatDataArrayPtr);

        // 释放资源
        $this->ffi->FreeSlice($chatDataArrayPtr);

        return $chatDataArray;
    }

    /**
     * 解析聊天数据结构
     */
    private function parseChatDataArray($chatDataArrayPtr) {
        // 根据SDK头文件定义结构
        $sliceType = FFI::type('struct Slice_t');
        $slice = $sliceType->cast($chatDataArrayPtr);

        $result = [];
        $chatDataType = FFI::type('struct ChatData*');

        for ($i = 0; $i < $slice->len; $i++) {
            $chatDataPtr = $slice->buf + $i * FFI::sizeof($chatDataType);
            $chatData = $chatDataType->cast($chatDataPtr);

            $data = [
                'seq' => $chatData->seq,
                'msgid' => $chatData->msgid,
                'publickey_ver' => $chatData->publickey_ver,
                'encrypt_random_key' => FFI::string($chatData->encrypt_random_key),
                'encrypt_chat_msg' => FFI::string($chatData->encrypt_chat_msg),
                'decrypted' => false,
                'content' => null
            ];

            // 尝试解密
            try {
                $data['content'] = $this->decryptData(
                    $data['encrypt_random_key'],
                    $data['encrypt_chat_msg']
                );
                $data['decrypted'] = true;
            } catch (Exception $e) {
                $data['decrypt_error'] = $e->getMessage();
            }

            $result[] = $data;
        }

        return $result;
    }

    /**
     * 解密聊天数据
     */
    public function decryptData($encryptRandomKey, $encryptChatMsg) {
        $privateKeyPath = config('wecom.private_key_path');
        if (!file_exists($privateKeyPath)) {
            throw new Exception("私钥文件不存在: $privateKeyPath");
        }

        $privateKey = file_get_contents($privateKeyPath);

        $decrypted = $this->ffi->DecryptData(
            $privateKey,
            $encryptRandomKey,
            $encryptChatMsg
        );

        if (FFI::isNull($decrypted)) {
            throw new Exception("数据解密失败");
        }

        $result = FFI::string($decrypted);
        $this->ffi->FreeSlice($decrypted);

        return $result;
    }

    public function __destruct() {
        if (!FFI::isNull($this->sdkPtr)) {
            $this->ffi->DestroySdk($this->sdkPtr);
        }
    }

}