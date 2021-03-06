<?php

namespace Drupal\parade_content_lister\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\node\NodeInterface;

/**
 * Class CardThumbnailBuilder.
 *
 * @package Drupal\parade_content_lister\Service
 */
class CardThumbnailBuilder {

  const IMAGE_STYLE = 'parade_card_thumbnail';
  const THUMBNAIL_PARENT_MODULE = 'parade_content_lister';
  const IMAGE_RELATIVE_PATH = 'styles/images/default-thumbnail.png';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config for the parade_content_lister module.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $moduleConfig;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * The image style to be used.
   *
   * @var \Drupal\image\Entity\ImageStyle
   */
  protected $cardImageStyle;

  /**
   * Logger channel for the module.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * CardThumbnailBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Image\ImageFactory $imageFactory
   *   Image factory service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   Logger channel factory.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ConfigFactoryInterface $configFactory,
    ImageFactory $imageFactory,
    LoggerChannelFactoryInterface $loggerChannelFactory
  ) {
    $this->moduleConfig = $configFactory->get('parade_content_lister.settings');
    $this->entityTypeManager = $entityTypeManager;
    $this->imageFactory = $imageFactory;
    $this->logger = $loggerChannelFactory->get('parade_content_lister');

    $this->cardImageStyle = $this->entityTypeManager->getStorage('image_style')->load(static::IMAGE_STYLE);

    $this->createDefaultDerivative();
  }

  /**
   * Creates the derivative for an image, if needed.
   */
  protected function createDerivative($imageUri) {
    $styleUri = $this->cardImageStyle->buildUri($imageUri);

    // Generate the styled image if it doesn't exist.
    if (!file_exists($styleUri)) {
      $this->cardImageStyle->createDerivative($imageUri, $styleUri);
    }
  }

  /**
   * Creates the derivative image for the default thumbnail.
   */
  protected function createDefaultDerivative() {
    $basePath = drupal_get_path('module', static::THUMBNAIL_PARENT_MODULE);

    /** @var \Drupal\Core\Image\Image $image */
    $image = $this->imageFactory->get($basePath . '/' . static::IMAGE_RELATIVE_PATH);
    $this->createDerivative($image->getSource());
  }

  /**
   * Updates the computed image field of a node.
   *
   * Note, this function doesn't save the node, only updates the
   * node object.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to be updated.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function updateNode(NodeInterface $node) {
    $thumbnail = $node->get('field_thumbnail')->getValue();
    $classes = [];
    if ($this->moduleConfig->get('pcl_vertical_center') === 1) {
      $classes[] = 'vertically-centered';
    }

    $derivativePath = NULL;
    $headerParagraph = NULL;

    if (isset($thumbnail[0])) {
      $fileId = $thumbnail[0]['target_id'];
      /** @var \Drupal\file\FileInterface $file */
      $file = $this->entityTypeManager->getStorage('file')
        ->load($fileId);

      if (NULL !== $file) {
        $path = $file->getFileUri();
        $url = $this->cardImageStyle->buildUrl($path);
        $thumbnailTag = "<img src='$url'/>";
        $derivativePath = $path;
      }
      else {
        $this->logger->error('File with ID @fid does not exist for node with ID @nid', [
          '@fid' => $fileId,
          '@nid' => (NULL === $node->id()) ? '"N/A (new content)"' : $node->id(),
        ]);
      }
    }
    else {
      $sections = $node->get('parade_onepage_sections')->getValue();
      foreach ($sections as $key => $value) {
        $revisionIds[] = $value['target_revision_id'];
      }
      if (isset($revisionIds)) {
        $query = $this->entityTypeManager->getStorage('paragraph')->getQuery();
        $query->condition('revision_id', $revisionIds, 'IN');
        $query->condition('type', 'header');
        $ent = $query->execute();
        foreach ($ent as $key => $value) {
          /** @var \Drupal\paragraphs\ParagraphInterface $headerEntity */
          $headerEntity = $this->entityTypeManager
            ->getStorage('paragraph')
            ->loadRevision($key);

          if ($headerEntity->isTranslatable()) {
            $headerEntity = $headerEntity->getTranslation($node->language()->getId());
          }

          if (
            NULL !== $headerEntity
            && $headerEntity->get('type')->getValue()[0]['target_id'] === 'header'
          ) {
            $headerParagraph = $headerEntity;
            break;
          }
        }
      }

      if (NULL !== $headerParagraph) {
        $headerBackground = $headerParagraph->get('parade_background')->getValue();
        $fileId = $headerBackground[0]['target_id'];
        /** @var \Drupal\file\FileInterface $file */
        $file = $this->entityTypeManager->getStorage('file')
          ->load($fileId);

        if (NULL !== $file) {
          $path = $file->getFileUri();
          $type = $file->getMimeType();
          $type = explode('/', $type)[0];

          if ($type === 'video') {
            $path = file_create_url($path);
            $thumbnailTag = '<video muted="" loop="" playsinline="">';
            $thumbnailTag .= "<source src='$path' type='video/mp4' codecs='avc1.42E01E, mp4a.40.2'>";
            $thumbnailTag .= '</video>';
          }
          else {
            $url = $this->cardImageStyle->buildUrl($path);
            $thumbnailTag = "<img src='$url'/>";
            $derivativePath = $path;
          }
        }
        else {
          $this->logger->error('File with ID @fid does not exist for header paragraph with ID @pid in node with ID @nid.', [
            '@fid' => $fileId,
            '@pid' => $headerParagraph->id(),
            '@nid' => (NULL === $node->id()) ? '"N/A (new content)"' : $node->id(),
          ]);
        }
      }
    }

    if (empty($thumbnailTag)) {
      $classes[] = 'vertically-centered';
      $imageUrl = '/' . drupal_get_path('module', 'parade_content_lister') . '/styles/images/default-thumbnail.png';
      $thumbnailTag = '<img src="' . $imageUrl . '" />';
      $derivativePath = $imageUrl;
    }

    if (NULL !== $derivativePath) {
      // Styled images don't get created by default,
      // we need to do it manually.
      $this->createDerivative($derivativePath);
    }

    // Not needed, add wrapper in view.
    // @todo: add wrapper to default view.
//    $thumb_height = $this->moduleConfig->get('pcl_thumbnail_height') . 'px';
//    $imageLink = $node->toLink(
//      Markup::create($thumbnailTag),
//      'edit-form',
//      [
//        'attributes' => [
//          'class' => $classes,
//          'style' => "height: $thumb_height;",
//        ],
//      ]
//    )->toString()->getGeneratedLink();

    $node->get('field_computed_image')->setValue([
      'value' => Markup::create($thumbnailTag),
      'format' => 'full_html',
    ]);
  }

  /**
   * Generate thumbnail images for every translation of a node by nid.
   *
   * Note, use this only when every translation of a node has to be updated.
   * E.g when bulk-updating.
   * Note, this saves the node.
   *
   * @param int|string $nid
   *   The node object.
   *
   * @return bool
   *   ?
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function build($nid) {
    /** @var \Drupal\node\NodeInterface $defaultNode */
    $defaultNode = $this->entityTypeManager->getStorage('node')->load($nid);

    if (NULL === $defaultNode) {
      // @todo: Maybe do smth else, too.
      return FALSE;
    }

    $languages = $defaultNode->getTranslationLanguages();
    foreach ($languages as $langcode => $language) {
      $node = $defaultNode->getTranslation($langcode);
      $this->updateNode($node);
      $node->setNewRevision(FALSE);
      $node->doNotForceNewRevision = TRUE;
      $node->save();
    }
    return TRUE;
  }

}
