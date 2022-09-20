<?php

namespace Drupal\media_remote_audio;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Overrides the media.oembed.resource_fetcher service.
 */
class MediaRemoteAudioServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container->getDefinition('media.oembed.resource_fetcher')
      ->setClass(SoundCloudAwareResourceFetcher::class);
  }

}
