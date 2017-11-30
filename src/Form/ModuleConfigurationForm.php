<?php

namespace Drupal\parade_content_lister\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures forms module settings.
 */
class ModuleConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'parade_content_lister_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'parade_content_lister.settings',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    // Config get for default values.
    $config = $this->config('parade_content_lister.settings');
    $thumbnail_height = $config->get('pcl_thumbnail_height');
    $centered = $config->get('pcl_vertical_center');

    /** @var \Drupal\node\NodeTypeInterface[] $contentTypes */
    $contentTypes = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();

    $contentTypesList = [];
    $contentTypesList['-none-'] = $this->t('-none-');
    foreach ($contentTypes as $contentType) {
      $contentTypesList[$contentType->id()] = $contentType->label();
    }
    $form['pcl_content_type'] = [
      '#type' => 'select',
      '#options' => $contentTypesList,
      '#title' => $this->t('Parade content lister content type:'),
      '#description' => $this->t('If -none- is selected parade content view filter takes over'),
      '#default_value' => $config->get('pcl_content_type'),
    ];
    $form['pcl_vertical_center'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verticaly centered thumbnails.'),
      '#description' => $this->t('If changed thumbnails will be automatically regenerated!'),
      '#default_value' => isset($centered) ? $centered : FALSE,
    ];
    $form['pcl_thumbnail_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Thumbnail height (px).'),
      '#description' => $this->t('If changed thumbnails will be automatically regenerated!'),
      '#default_value' => isset($thumbnail_height) ? $thumbnail_height : '222',
    ];
    $form['pcl_regenerate_thumbnails'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Regenerate thumbnails'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Config\ConfigValueException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $regen = FALSE;
    $config = $this->config('parade_content_lister.settings');
    if ($config->get('pcl_thumbnail_height') !== $form_state->getValue('pcl_thumbnail_height') ||
      $config->get('pcl_vertical_center') !== $form_state->getValue('pcl_vertical_center')) {
      $regen = TRUE;
    }
    $this->configFactory()->getEditable('parade_content_lister.settings')
      // Set the submitted configuration setting.
      ->set('pcl_content_type', $form_state->getValue('pcl_content_type'))
      ->set('pcl_thumbnail_height', $form_state->getValue('pcl_thumbnail_height'))
      ->set('pcl_vertical_center', $form_state->getValue('pcl_vertical_center'))
      ->save();

    if ($regen || $form_state->getValue('pcl_regenerate_thumbnails')) {
      $nids = \Drupal::entityQuery('node')->condition('type', 'campaign')->execute();
      $batch = [
        'title' => t('Generating thumbnails...'),
        'init_message' => t('Initializing.'),
        'progress_message' => t('Completed @current of @total.'),
        'operations' => [
          [
            '\Drupal\parade_content_lister\ParadeContentListerRunBatch::generateImages',
            [$nids],
          ],
        ],
        'finished' => '\Drupal\parade_content_lister\ParadeContentListerRunBatch::generateImageFinished',
      ];
      batch_set($batch);
    }
  }

}
