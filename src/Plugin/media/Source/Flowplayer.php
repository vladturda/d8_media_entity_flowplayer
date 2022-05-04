<?php

namespace Drupal\d8_media_entity_flowplayer\Plugin\media\Source;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides media type plugin for Flowplayer.
 *
 * @MediaSource(
 *   id = "flowplayer",
 *   label = @Translation("Flowplayer"),
 *   description = @Translation("Provides business logic and metadata for Flowplayer."),
 *   allowed_field_types = {
 *     "string"
 *   },
 *   default_thumbnail_filename = "flowplayer.png",
 *   default_name_metadata_attribute = "default_name",
 * )
 */
class Flowplayer extends MediaSourceBase
{
    /**
     * Base URL for Flowplayer API
     *
     * @var string
     */
    protected $apiBase;

    /**
     * API Key.
     *
     * @var string
     */
    protected $apiKey;

    /**
     * Site ID.
     *
     * @var string
     */
    protected $siteId;

    /**
     * The HTTP client.
     *
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * The file system.
     *
     * @var \Drupal\Core\File\FileSystemInterface
     */
    protected $fileSystem;

    /**
     * Constructs a new class instance.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   Entity type manager service.
     * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
     *   Entity field manager service.
     * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
     *   The field type plugin manager service.
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   Config factory service.
     * @param \GuzzleHttp\ClientInterface $http_client
     *   The HTTP client.
     * @param \Drupal\Core\File\FileSystemInterface $file_system
     *   The file system.
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition,
                                EntityTypeManagerInterface $entity_type_manager,
                                EntityFieldManagerInterface $entity_field_manager,
                                FieldTypePluginManagerInterface $field_type_manager,
                                ConfigFactoryInterface $config_factory,
                                ClientInterface $http_client,
                                FileSystemInterface $file_system = NULL) {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);

        $this->apiBase = 'https://api.flowplayer.com/ovp/web/video/v2';
        $this->apiKey = $config_factory->get('d8_media_entity_flowplayer.settings')->get('api_key');
        $this->siteId = $config_factory->get('d8_media_entity_flowplayer.settings')->get('site_id');
        $this->httpClient = $http_client;

        if (!$file_system) {
            @trigger_error('The file_system service must be passed to Flowplayer::__construct(), it is required before Drupal 9.0.0. See https://www.drupal.org/node/3006851.', E_USER_DEPRECATED);
            $file_system = \Drupal::service('file_system');
        }
        $this->fileSystem = $file_system;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('entity_type.manager'),
            $container->get('entity_field.manager'),
            $container->get('plugin.manager.field.field_type'),
            $container->get('config.factory'),
            $container->get('http_client'),
            $container->get('file_system')
        );
    }

    /**
     * Runs preg_match on embed code/URL.
     * This function isn't being used, left here for reference.
     *
     * @param \Drupal\media\MediaInterface $media
     *   Media object.
     *
     * @return array|bool
     *   Array of preg matches or FALSE if no match.
     *
     * @see preg_match()
     */
    protected function matchRegexp(MediaInterface $media)
    {
        $matches = [];

        if (isset($this->configuration['source_field'])) {
            $source_field = $this->configuration['source_field'];

            if ($media->hasField($source_field)) {
                $property_name = $media->{$source_field}->first()->mainPropertyName();

                foreach (static::$validationRegexp as $pattern => $key) {
                    if (preg_match($pattern, $media->{$source_field}->{$property_name}, $matches)) {
                        return $matches;
                    }
                }
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(MediaInterface $media, $attribute_name)
    {
        $source = $media->get($this->configuration['source_field']);
        $video_id = $source->value;

        if (isset($this->apiKey)) {
            $request_url = "{$this->apiBase}/{$video_id}.json?api_key={$this->apiKey}";

            try {
                $request = $this->httpClient->request('GET', $request_url);
                $response = json_decode($request->getBody());
                //ksm($response);

                switch ($attribute_name) {
                    case 'default_name':
                        return $response->name;

                    case 'description':
                        return $response->description;

                    case 'duration':
                        $duration = $response->duration;
                        $seconds = $duration % 60;
                        $minutes = ($duration - $seconds) / 60;
                        //return "PT{$minutes}M{$seconds}S";
                        return $response->duration;

                    case 'video_uri':
                        return $response->mediafiles->original_file_url;

                    case 'thumbnail_uri':
                        return $this->getLocalThumbnailUri($response->images->normal_image_url) ?: parent::getMetadata($media, 'thumbnail_uri');
                }
            } catch (RequestException $e) {
                watchdog_exception('d8_media_entity_flowplayer', $e);
            }
        } else {
            drupal_set_message(t('Please set API key for Flowplayer.'), 'warning');
        }

        return NULL;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataAttributes()
    {
        return [
            'default_name' => $this->t('Name'),
            'description' => $this->t('Description'),
            'duration' => $this->t('Duration'),
            'video_uri' => $this->t('Video URL'),
            'thumbnail_uri' => $this->t('Thumbnail URL'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
        return [
            'thumbnails_directory' => 'public://flowplayer_thumbnails'
        ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    protected function getLocalThumbnailUri($thumbnail_uri)
    {
        // If there is no remote thumbnail, there's nothing for us to fetch here.
        $remote_thumbnail_url = $thumbnail_uri;
        if (!$remote_thumbnail_url) {
            return null;
        }

        // Compute the local thumbnail URI, regardless of whether or not it exists.
        $configuration = $this->getConfiguration();
        $directory = $configuration['thumbnails_directory'];
        $local_thumbnail_uri = "$directory/" . Crypt::hashBase64($remote_thumbnail_url) . '.' . pathinfo($remote_thumbnail_url, PATHINFO_EXTENSION);

        // If the local thumbnail already exists, return its URI.
        if (file_exists($local_thumbnail_uri)) {
            return $local_thumbnail_uri;
        }

        // The local thumbnail doesn't exist yet, so try to download it. First,
        // ensure that the destination directory is writable, and if it's not,
        // log an error and bail out.
        if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
            $this->logger->warning('Could not prepare thumbnail destination directory @dir for Flowplayer media.', [
                '@dir' => $directory,
            ]);
            return null;
        }

        try {
            $response = $this->httpClient->get($remote_thumbnail_url);
            if ($response->getStatusCode() === 200) {
                $this->fileSystem->saveData((string) $response->getBody(), $local_thumbnail_uri, FileSystemInterface::EXISTS_REPLACE);
                return $local_thumbnail_uri;
            }
        } catch (RequestException $e) {
            $this->logger->warning($e->getMessage());
        } catch (FileException $e) {
            $this->logger->warning('Could not download remote thumbnail from {url}.', [
                'url' => $remote_thumbnail_url,
            ]);
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function createSourceField(MediaTypeInterface $media_type)
    {
        return parent::createSourceField($media_type)
            ->set('label', $this->t('Flowplayer Video ID'));
    }

}
