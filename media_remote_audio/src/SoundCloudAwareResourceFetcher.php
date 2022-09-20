<?php

namespace Drupal\media_remote_audio;

use Drupal\media\OEmbed\ResourceFetcher;

/**
 * Extends the oEmbed resource fetcher with SoundCloud-specific workarounds.
 *
 * The service must be extended because there is currently no way to inject a
 * specialized XML resource parser to handle SoundCloud-specific quirks.
 */
class SoundCloudAwareResourceFetcher extends ResourceFetcher {

  /**
   * {@inheritdoc}
   */
  protected function parseResourceXml($data, $url) {
    $data = parent::parseResourceXml($data, $url);

    if (isset($data['provider-name']) && $data['provider-name'] === 'SoundCloud') {
      foreach ($data as $key => $value) {
        unset($data[$key]);
        $key = str_replace('-', '_', $key);
        $data[$key] = $value;
      }

      // SoundCloud might not include thumbnail dimensions, which can cause an
      // exception because core will not currently allow a thumbnail with
      // unknown dimensions. If no dimensions are included, try to determine
      // them by parsing the thumbnail URL. Otherwise, fall back to default
      // hard-coded dimensions.
      $data += [
        'thumbnail_url' => NULL,
      ];
      if (isset($data['thumbnail_url']) && empty($data['thumbnail_width']) && empty($data['thumbnail_height'])) {
        $matched = [];
        // If the thumbnail URL includes dimensions, use those. We expect
        // it to end in the format like "...500x500.jpg".
        if (preg_match('/[^0-9]([0-9]+)x([0-9]+)\..*$/i', $data['thumbnail_url'], $matched)) {
          $data['thumbnail_width'] = (int) $matched[1];
          $data['thumbnail_height'] = (int) $matched[2];
        }
        else {
          // Set a default width and height for the thumbnail.
          $data['thumbnail_width'] = 200;
          $data['thumbnail_height'] = 200;
        }
      }
    }
    return $data;
  }

}
