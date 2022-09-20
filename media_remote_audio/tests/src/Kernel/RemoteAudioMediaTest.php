<?php

namespace Drupal\Tests\media_remote_audio\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\MediaInterface;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;

/**
 * @group media_remote_audio
 */
class RemoteAudioMediaTest extends KernelTestBase {

  /**
   * Responses or exceptions to serve to the mocked HTTP client, keyed by URI.
   *
   * @var \Psr\Http\Message\ResponseInterface[]|\Throwable[]
   */
  private $responses = [];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'image',
    'media',
    'media_remote_audio',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set up the database so that we can create remote audio media items.
    $this->installConfig('media');
    $this->installConfig('media_remote_audio');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('user');
    $this->installSchema('file', ['file_usage']);

    // Make the mocked HTTP client serve the providers database from our
    // fixtures.
    $url = $this->config('media.settings')->get('oembed_providers_url');
    $this->assertNotEmpty($url);
    $this->responses[$url] = new Response(200, [], Utils::tryFopen(__DIR__ . '/../../fixtures/providers.json', 'r'));
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    // Mock the HTTP client as early as possible (i.e., while the container is
    // being built initially), to ensure it is used consistently in this test,
    // and give it the `persist` tag to ensure it survives container rebuilds.
    $client = new Client([
      'handler' => HandlerStack::create($this),
    ]);
    $this->container->set('http_client', $client);
    $container->getDefinition('http_client')->addTag('persist');
  }

  /**
   * Data provider for ::testRemoteAudioMedia().
   *
   * @return array[]
   *   The test cases.
   */
  public function providerRemoteAudioMedia(): array {
    return [
      'iHeartRadio' => [
        'https://www.iheart.com/podcast/270-crime-junkie-29319113/episode/murdered-jenny-lin-part-2-101263716/',
        __DIR__ . '/../../fixtures/iHeartRadio.json',
      ],
      'Spotify' => [
        'https://open.spotify.com/album/42YnG5klTs8VUflewCZamw',
        __DIR__ . '/../../fixtures/Spotify.json',
      ],
    ];
  }

  /**
   * Tests creating remote audio media from various providers.
   *
   * @param string $url
   *   The URL of the remote media to add. In a real site, this would be used to
   *   create the media in the UI.
   * @param string $fixture_file
   *   The path of the local file containing the oEmbed resource data.
   *
   * @dataProvider providerRemoteAudioMedia
   */
  public function testRemoteAudioMedia(string $url, string $fixture_file): void {
    $this->createRemoteAudioMedia($url, $fixture_file);
  }

  /**
   * Data provider for ::testSoundCloudThumbnailSize().
   *
   * @return array[]
   *   The test cases.
   */
  public function providerSoundCloudThumbnailSize(): array {
    return [
      'thumbnail size known' => [
        __DIR__ . '/../../fixtures/SoundCloud-thumbnail_size_in_url.xml',
        500,
        500,
      ],
      'thumbnail size not known' => [
        __DIR__ . '/../../fixtures/SoundCloud-no_thumbnail_size.xml',
        200,
        200,
      ],
    ];
  }

  /**
   * Tests thumbnail size resolution when creating SoundCloud media.
   *
   * @param string $fixture_file
   *   The path of the local file containing the oEmbed resource data.
   * @param int $expected_thumbnail_width
   *   The expected width of the thumbnail.
   * @param int $expected_thumbnail_height
   *   The expected height of the thumbnail.
   *
   * @dataProvider providerSoundCloudThumbnailSize
   */
  public function testSoundCloudThumbnailSize(string $fixture_file, int $expected_thumbnail_width, int $expected_thumbnail_height): void {
    $media = $this->createRemoteAudioMedia('https://soundcloud.com/dj-aphrodite/drum-and-bass-style-dj-studio-mix-2016-2017', $fixture_file);
    $source = $media->getSource();
    $this->assertSame($expected_thumbnail_width, $source->getMetadata($media, 'thumbnail_width'));
    $this->assertSame($expected_thumbnail_height, $source->getMetadata($media, 'thumbnail_height'));
  }

  /**
   * Creates a remote audio media item.
   *
   * @param string $url
   *   The URL of the remote media to add. In a real site, this would be used to
   *   create the media in the UI.
   * @param string $fixture_file
   *   The path of the local file containing the oEmbed resource data.
   *
   * @return \Drupal\media\MediaInterface
   *   The created media item.
   */
  private function createRemoteAudioMedia(string $url, string $fixture_file): MediaInterface {
    // Ensure the resource data can be fetched by the mocked HTTP client.
    $resource_url = $this->container->get('media.oembed.url_resolver')
      ->getResourceUrl($url);
    $headers = [
      'Content-Type' => pathinfo($fixture_file, PATHINFO_EXTENSION) === 'xml'
        ? 'text/xml'
        : 'application/json',
    ];
    $this->responses[$resource_url] = new Response(200, $headers, Utils::tryFopen($fixture_file, 'r'));

    // Ensure the the thumbnail can be fetched by the mock HTTP client.
    /** @var \Drupal\media\OEmbed\ResourceFetcherInterface $fetcher */
    $fetcher = $this->container->get('media.oembed.resource_fetcher');
    $thumbnail_url = $fetcher->fetchResource($resource_url)
      ->getThumbnailUrl();
    $this->assertNotEmpty($thumbnail_url);
    $thumbnail_url = $thumbnail_url->toString();
    $thumbnail_file = $this->getDrupalRoot() . '/core/misc/druplicon.png';
    $headers = [
      'Content-Type' => 'image/png',
    ];
    $this->responses[$thumbnail_url] = new Response(200, $headers, Utils::tryFopen($thumbnail_file, 'r'));

    /** @var \Drupal\media\MediaTypeInterface $media_type */
    $media_type = $this->container->get('entity_type.manager')
      ->getStorage('media_type')
      ->load('remote_audio');
    $source_field = $media_type->getSource()
      ->getSourceFieldDefinition($media_type)
      ->getName();

    // Ensure we can create a remote audio media item from the given fixture.
    $media = $this->container->get('entity_type.manager')
      ->getStorage('media')
      ->create([
        'bundle' => 'remote_audio',
        $source_field => $url,
      ]);
    $media->save();

    return $media;
  }

  /**
   * Handler function for our mocked HTTP client.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request being handled.
   * @param array $options
   *   The request options.
   *
   * @return \GuzzleHttp\Promise\PromiseInterface
   *   A resolved or rejected promise, depending on what's in $this->responses
   *   for the given request URI.
   */
  public function __invoke(RequestInterface $request, array $options): PromiseInterface {
    $uri = (string) $request->getUri();

    if (array_key_exists($uri, $this->responses)) {
      $response = $this->responses[$uri];

      // If the response is an exception or HTTP error, return a rejected
      // promise.
      if ($response instanceof \Throwable || $response->getStatusCode() >= 400) {
        return Create::rejectionFor($response);
      }
      else {
        return Create::promiseFor($response);
      }
    }
    else {
      return Create::rejectionFor("Unknown URI: $uri");
    }
  }

}
