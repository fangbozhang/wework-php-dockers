<?php
namespace app\job;

use think\queue\Job;
use WxworkFinanceSdk;
use think\facade\Db;

class WechatMediaDownload {
    public function fire(Job $job, $data) {
        try {
            $company = Db::name('company_config')->find($data['company_id']);
            $sdk = new WxworkFinanceSdk($company->corp_id, $company->corp_secret);

            $file = $sdk->getMedia($data['sdkfileid']);
            $savePath = $this->saveMedia($file, $data);

            Db::name($company->chat_table)
                ->where('seq', $data['seq'])
                ->update([
                    'media_path' => $savePath,
                    'status' => 2
                ]);

            $job->delete();
        } catch (\Exception $e) {
            $job->release(300); // 5分钟后重试
        }
    }

    protected function saveMedia($file, $data) {
        $path = "storage/media/{$data['company_id']}/" . date('Ym/d');
        if (!is_dir($path)) mkdir($path, 0755, true);

        $filename = "{$data['msgtype']}_{$data['seq']}." . $this->getExtension($data['msgtype']);
        file_put_contents("$path/$filename", $file);

        return "$path/$filename";
    }
}
