<?php

namespace Drupal\parade_content_lister;

/**
 * Class ParadeContentListerRunBatch.
 *
 * @package Drupal\parade_content_lister
 */
class ParadeContentListerRunBatch {

  /**
   * Batch op to save images.
   *
   * @param array $nids
   *   Gets node ids.
   * @param array $context
   *   Context.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public static function generateImages(array $nids, array &$context) {
    /** @var \Drupal\parade_content_lister\Service\CardThumbnailBuilder $cardThumbnailBuilder */
    $cardThumbnailBuilder = \Drupal::service('parade_content_lister.card_thumbnail_builder');
    $message = 'Generating...';
    $results = [];
    foreach ($nids as $nid) {
      $results[] = $cardThumbnailBuilder->build($nid);
    }

    $context['message'] = $message;
    $context['results'] = $results;
  }

  /**
   * Batch op finish function.
   */
  public static function generateImageFinished($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        // Use \count instead of count for opcode optimization.
        \count($results),
        'Thumbnail generatedOne post processed.', '@count thumbnails generated.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    drupal_set_message($message);
  }

}
