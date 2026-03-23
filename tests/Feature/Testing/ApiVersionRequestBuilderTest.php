<?php

use GaiaTools\ContentAccord\Resolvers\Version\AcceptHeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\HeaderVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\QueryStringVersionResolver;
use GaiaTools\ContentAccord\Resolvers\Version\UriVersionResolver;
use GaiaTools\ContentAccord\Testing\ApiVersionRequestBuilder;
use GaiaTools\ContentAccord\Testing\Concerns\InteractsWithApiVersion;
use Illuminate\Foundation\Testing\TestCase as FoundationTestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Create a mock TestCase that records the last HTTP call made through it.
 *
 * @return MockInterface&FoundationTestCase
 */
function makeFoundationTestCaseMock(): MockInterface
{
    $mock = Mockery::mock(FoundationTestCase::class);
    $fakeResponse = new TestResponse(new Response('ok', 200));

    $mock->shouldReceive('get')->andReturn($fakeResponse)->byDefault();
    $mock->shouldReceive('post')->andReturn($fakeResponse)->byDefault();
    $mock->shouldReceive('put')->andReturn($fakeResponse)->byDefault();
    $mock->shouldReceive('patch')->andReturn($fakeResponse)->byDefault();
    $mock->shouldReceive('delete')->andReturn($fakeResponse)->byDefault();
    $mock->shouldReceive('json')->andReturn($fakeResponse)->byDefault();

    return $mock;
}

// --- URI resolver strategy (default / fallback) ---

test('builder injects version prefix in URI for GET with URI resolver', function () {
    config([
        'content-accord.versioning.resolver' => UriVersionResolver::class,
        'content-accord.versioning.strategies.uri.prefix' => 'v',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri) {
            return str_starts_with($uri, '/v1') || str_contains($uri, 'v1');
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->get('/items');
});

test('builder injects version prefix in URI for POST with URI resolver', function () {
    config([
        'content-accord.versioning.resolver' => UriVersionResolver::class,
        'content-accord.versioning.strategies.uri.prefix' => 'v',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('post')
        ->once()
        ->withArgs(function ($uri) {
            return str_starts_with($uri, '/v2') || str_contains($uri, 'v2');
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '2');
    $builder->post('/items', ['key' => 'value']);
});

test('builder injects version prefix in URI for PUT with URI resolver', function () {
    config([
        'content-accord.versioning.resolver' => UriVersionResolver::class,
        'content-accord.versioning.strategies.uri.prefix' => 'v',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('put')
        ->once()
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->put('/items/5');
});

test('builder injects version prefix in URI for PATCH with URI resolver', function () {
    config([
        'content-accord.versioning.resolver' => UriVersionResolver::class,
        'content-accord.versioning.strategies.uri.prefix' => 'v',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('patch')
        ->once()
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->patch('/items/5');
});

test('builder injects version prefix in URI for DELETE with URI resolver', function () {
    config([
        'content-accord.versioning.resolver' => UriVersionResolver::class,
        'content-accord.versioning.strategies.uri.prefix' => 'v',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('delete')
        ->once()
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->delete('/items/5');
});

test('builder injects version prefix for json method with URI resolver', function () {
    config([
        'content-accord.versioning.resolver' => UriVersionResolver::class,
        'content-accord.versioning.strategies.uri.prefix' => 'v',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('json')
        ->once()
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->json('POST', '/items');
});

test('builder uses array resolver with URI resolver first', function () {
    config([
        'content-accord.versioning.resolver' => [UriVersionResolver::class, HeaderVersionResolver::class],
        'content-accord.versioning.strategies.uri.prefix' => 'v',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri) {
            return str_starts_with($uri, '/v1');
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->get('/items');
});

// --- URI resolver finds matching route ---

test('builder resolves existing versioned URI route', function () {
    config([
        'content-accord.versioning.resolver' => UriVersionResolver::class,
        'content-accord.versioning.strategies.uri.prefix' => 'v',
    ]);

    Route::get('/v1/users', fn () => 'ok')->setAction(['api_version' => '1']);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->with('/v1/users', Mockery::any())
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->get('/users');
});

// --- Header resolver strategy ---

test('builder adds version header for GET with header resolver', function () {
    config([
        'content-accord.versioning.resolver' => HeaderVersionResolver::class,
        'content-accord.versioning.strategies.header.name' => 'Api-Version',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri, $headers) {
            return $headers['Api-Version'] === '2';
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '2');
    $builder->get('/items');
});

test('builder adds version header for POST with header resolver', function () {
    config([
        'content-accord.versioning.resolver' => HeaderVersionResolver::class,
        'content-accord.versioning.strategies.header.name' => 'Api-Version',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('post')
        ->once()
        ->withArgs(function ($uri, $data, $headers) {
            return $headers['Api-Version'] === '3';
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '3');
    $builder->post('/items', ['x' => 1]);
});

test('builder uses default header name when strategy config missing for header resolver', function () {
    config([
        'content-accord.versioning.resolver' => HeaderVersionResolver::class,
        'content-accord.versioning.strategies' => [],
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri, $headers) {
            return isset($headers['Api-Version']);
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->get('/items');
});

test('builder uses default header name when name is empty string', function () {
    config([
        'content-accord.versioning.resolver' => HeaderVersionResolver::class,
        'content-accord.versioning.strategies.header.name' => '',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri, $headers) {
            return isset($headers['Api-Version']);
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->get('/items');
});

// --- Accept header resolver strategy ---

test('builder adds accept header for GET with accept resolver', function () {
    config([
        'content-accord.versioning.resolver' => AcceptHeaderVersionResolver::class,
        'content-accord.versioning.strategies.accept.vendor' => 'testapp',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri, $headers) {
            return isset($headers['Accept']) && str_contains($headers['Accept'], 'testapp');
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->get('/items');
});

test('builder appends to existing Accept header with accept resolver', function () {
    config([
        'content-accord.versioning.resolver' => AcceptHeaderVersionResolver::class,
        'content-accord.versioning.strategies.accept.vendor' => 'testapp',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri, $headers) {
            return isset($headers['Accept'])
                && str_contains($headers['Accept'], 'application/json')
                && str_contains($headers['Accept'], 'testapp');
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->get('/items', ['Accept' => 'application/json']);
});

test('builder uses default vendor when vendor is empty string for accept resolver', function () {
    config([
        'content-accord.versioning.resolver' => AcceptHeaderVersionResolver::class,
        'content-accord.versioning.strategies.accept.vendor' => '',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri, $headers) {
            return isset($headers['Accept']) && str_contains($headers['Accept'], 'myapp');
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->get('/items');
});

// --- Query string resolver strategy ---

test('builder adds query param for GET with query string resolver', function () {
    config([
        'content-accord.versioning.resolver' => QueryStringVersionResolver::class,
        'content-accord.versioning.strategies.query.parameter' => 'version',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri) {
            return str_contains($uri, 'version=1');
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->get('/items');
});

test('builder appends query param when URI already has query string', function () {
    config([
        'content-accord.versioning.resolver' => QueryStringVersionResolver::class,
        'content-accord.versioning.strategies.query.parameter' => 'v',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri) {
            return str_contains($uri, 'filter=active') && str_contains($uri, 'v=2');
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '2');
    $builder->get('/items?filter=active');
});

test('builder uses default query parameter name when config missing', function () {
    config([
        'content-accord.versioning.resolver' => QueryStringVersionResolver::class,
        'content-accord.versioning.strategies' => [],
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri) {
            return str_contains($uri, 'version=');
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->get('/items');
});

test('builder falls back to default prefix v when prefix is empty string', function () {
    config([
        'content-accord.versioning.resolver' => UriVersionResolver::class,
        'content-accord.versioning.strategies.uri.prefix' => '', // empty → falls back to 'v'
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri) {
            return str_starts_with($uri, '/v1');
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->get('/items');
});

test('builder continues past routes with non-matching major version', function () {
    config([
        'content-accord.versioning.resolver' => UriVersionResolver::class,
        'content-accord.versioning.strategies.uri.prefix' => 'v',
    ]);

    // Register a v2 route — requesting v1 should skip this and fall back to injectVersionSegment
    Route::get('/v2/items', fn () => 'ok')->setAction(['api_version' => '2']);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri) {
            return str_starts_with($uri, '/v1'); // injectVersionSegment called, not the v2 route
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1'); // target major=1
    $builder->get('/items');
});

test('builder falls back to default query param name when param is empty string', function () {
    config([
        'content-accord.versioning.resolver' => QueryStringVersionResolver::class,
        'content-accord.versioning.strategies.query.parameter' => '', // empty → falls back to 'version'
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri) {
            return str_contains($uri, 'version=1');
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->get('/items');
});

test('injectVersionSegment handles empty uri (root path)', function () {
    config([
        'content-accord.versioning.resolver' => UriVersionResolver::class,
        'content-accord.versioning.strategies.uri.prefix' => 'v',
    ]);

    $mock = makeFoundationTestCaseMock();
    $mock->shouldReceive('get')
        ->once()
        ->withArgs(function ($uri) {
            return $uri === '/v1'; // empty uri → '/' + versionSegment only
        })
        ->andReturn(new TestResponse(new Response('ok')));

    $builder = new ApiVersionRequestBuilder($mock, '1');
    $builder->get('/'); // ltrim('/') → '' → injectVersionSegment('', 'v') → '/v1'
});

// A named helper class so Mockery can create a partial mock that uses the real trait method.
// It must extend FoundationTestCase to satisfy ApiVersionRequestBuilder's type hint.
class InteractsWithApiVersionTestWrapper extends FoundationTestCase
{
    use InteractsWithApiVersion;
}

// --- InteractsWithApiVersion trait ---

test('InteractsWithApiVersion::withApiVersion creates builder with $this as test case', function () {
    // makePartial() lets the real withApiVersion implementation run on the mock instance.
    // $this inside the trait is the mock, which IS a FoundationTestCase — satisfying the
    // ApiVersionRequestBuilder constructor's type hint.
    $mock = Mockery::mock(InteractsWithApiVersionTestWrapper::class)->makePartial();

    $builder = $mock->withApiVersion('2');

    expect($builder)->toBeInstanceOf(ApiVersionRequestBuilder::class);
});
