<?php

namespace Drupal\Tests\media_remote_audio\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests integration with the Media Library module.
 *
 * @group media_remote_audio
 */
class MediaLibraryIntegrationTest extends WebDriverTestBase {

  use EntityReferenceTestTrait;
  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media_library',
    'media_remote_audio',
    'node',
  ];

  /**
   * Tests integration with the core media library.
   */
  public function testMediaLibraryIntegration() {
    $node_type = $this->drupalCreateContentType()->id();
    $this->createEntityReferenceField('node', $node_type, 'field_media', 'Media', 'media');

    $this->container->get('entity_display.repository')
      ->getFormDisplay('node', $node_type)
      ->setComponent('field_media', [
        'type' => 'media_library_widget',
      ])
      ->save();

    $account = $this->drupalCreateUser([
      'create media',
      "create $node_type content",
    ]);
    $this->drupalLogin($account);
    $this->drupalGet("/node/add/$node_type");
    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Add media')->press();
    $this->assertNotEmpty($assert_session->waitForField('Add Remote audio via URL'));
  }

}
