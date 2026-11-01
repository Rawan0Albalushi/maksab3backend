<?php

namespace App\Services;

use Brian2694\Toastr\Toastr;

class NullSafeToastr extends Toastr
{
    public function message()
    {
        $messages = $this->session->get('toastr::messages');

        if (! $messages) {
            $messages = [];
        }

        $script = '<script type="'.$this->jsType.'">';

        foreach ($messages as $message) {
            $config = (array) $this->config->get('toastr.options');

            if (count($message['options'] ?? [])) {
                $config = array_merge($config, $message['options']);
            }

            if ($config) {
                $script .= 'toastr.options = '.json_encode($config).';';
            }

            $title = addslashes($message['title'] ?? '') ?: null;

            $script .= 'toastr.'.$message['type'].
                '(\''.addslashes($message['message'] ?? '').
                "','$title".
                '\');';
        }

        $script .= '</script>';

        return $script;
    }
}
