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
   * @param string $contentType
   *   Get node bundle.
   * @param array $context
   *   Context.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public static function generateImages($contentType, array &$context) {
    /** @var \Drupal\parade_content_lister\Service\CardThumbnailBuilder $cardThumbnailBuilder */
    $cardThumbnailBuilder = \Drupal::service('parade_content_lister.card_thumbnail_builder');
    $results = [];
    $limit = 20;

    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_id'] = 0;
      $context['sandbox']['max'] = db_query('SELECT COUNT(DISTINCT nid) FROM {node} WHERE type=:type', [':type' => $contentType])->fetchField();
    }
    $nids = \Drupal::entityQuery('node')->condition('type', $contentType)->range(0, $limit)->execute();
    foreach ($nids as $nid) {
      $context['sandbox']['progress']++;
      $context['sandbox']['current_id'] = $nid;
      $results[] = $cardThumbnailBuilder->build($nid);
    }

    if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
      $context['message'] = 'Generated: ' . $context['sandbox']['progress'] . '/' . $context['sandbox']['max'];
    }
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
