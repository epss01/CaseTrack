<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if (strtoupper($method) === 'POST' && parse_url($uri, PHP_URL_PATH) === '/cases/import'
            && ! array_key_exists('_import_id', $parameters)) {
            $parameters['_import_id'] = $this->app['session.store']->get('case_import.id');
        }
        $json = is_string($content) ? json_decode($content, true) : null;
        if (is_array($json) && ! array_key_exists('_submission_id', $json)) {
            $json['_submission_id'] = (string) Str::uuid();
            $content = json_encode($json, JSON_THROW_ON_ERROR);
        }
        // Ordinary fixture submissions represent a freshly rendered form; retry tests pass their own ID.
        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && ! array_key_exists('_submission_id', $parameters)) {
            $parameters['_submission_id'] = (string) Str::uuid();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
