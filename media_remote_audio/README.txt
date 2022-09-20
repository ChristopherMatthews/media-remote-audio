Media Remote Audio
------------------
A simple module that extends oEmbed support added to Drupal Core's Media module
by implementing hook_media_source_info_alter() for the following providers:
* SoundCloud
* Spotify
* iHeartRadio


Requirements
------------------
Drupal Core Media (media) module


Installation
------------------
composer require 'drupal/media_remote_audio:^1.0'


Features
------------------
Media type: Creates a 'Remote audio' Media type
Manage fields: Creates an 'Audio URL' (field_media_oembed_audio) plain text field
Manage form display: Uses the 'oEmbed URL' widget
Manage display Uses the 'oEmbed content' widget


Usage:
------------------
To add Remote audio content, simply go to /media/add/remote_audio and paste the audio url from Spotify, SoundCloud, or iHeartRadio.
