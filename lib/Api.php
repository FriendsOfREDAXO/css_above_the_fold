<?php

/**
 * This file is part of the CssAboveTheFold package.
 *
 * @author Friends Of REDAXO
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


class rex_api_cssabove_api extends rex_api_function
{
    protected $response  = [];
    protected $published = true;
    protected $success   = true;

    public function execute()
    {
        $method  = rex_request('method', 'string', null);
        $_method = '__' . $method;

        if (!$method || !method_exists($this, $_method)) {
            throw new rex_api_exception("Method '{$method}' doesn't exist");
        }
        try {
            $this->$_method();
        }
        catch (ErrorException $ex) {
            throw new rex_api_exception($ex->getMessage());
        }
        $this->response['method'] = strtolower($method);
        return new rex_api_result($this->success, $this->response);
    }

    private function __savecss()
    {
        $token      = rex_post('token', 'string');
        $css        = rex_post('css', 'string');
        $device     = rex_post('device', 'string');
        $article_id = (int) rex_post('aid', 'int', 0);
        $lang_id    = (int) rex_post('lang_id', 'int', 0);
        $config     = rex::getConfig('cssabove', ['token' => '']);

        if ($token == '' || $token != $config['token']) {
            throw new rex_api_exception('Token not valid');
        }
        else if (!$article_id) {
            throw new rex_api_exception('Article Id not set');
        }
        else if (!$lang_id) {
            throw new rex_api_exception('Lang Id not set');
        }
        else if ($device == '') {
            throw new rex_api_exception('Device not set');
        }
        else if ($css == '') {
            throw new rex_api_exception('CSS empty');
        }
        $device = in_array($device, ['mobile', 'desktop']) ? $device : 'fraud' . date('YmdHis');
        $file   = rex_path::addonCache('cssabove', $device . '_' . $article_id . '_' . $lang_id . '.css');

        rex_file::put($file, stripslashes($css));
        rex::setConfig('cssabove', $config);
    }
}