<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

/**
 * @method TestResponse getJson(string $uri, array $headers = [])
 * @method TestResponse postJson(string $uri, array $data = [], array $headers = [])
 * @method TestResponse putJson(string $uri, array $data = [], array $headers = [])
 * @method TestResponse patchJson(string $uri, array $data = [], array $headers = [])
 * @method TestResponse deleteJson(string $uri, array $data = [], array $headers = [])
 */
abstract class TestCase extends BaseTestCase
{
    //
}
