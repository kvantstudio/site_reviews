<?php

namespace Drupal\site_reviews\TwigExtension;

use Drupal\node\Entity\Node;
use Drupal\site_reviews\Controller\SiteReviewsController;

/**
 * Twig extension that adds a custom function and a custom filter.
 */
class SiteReviewsTwigExtension extends \Twig_Extension {

  /**
   * In this function we can declare the extension function
   */
  public function getFunctions() {
    return array(
      new \Twig_SimpleFunction('getReviewForm', array($this, 'getReviewForm'), array('is_safe' => array('html'))),
      new \Twig_SimpleFunction('getReview', array($this, 'getReview')),
      new \Twig_SimpleFunction('getReviewsListByStaffer', array($this, 'getReviewsListByStaffer')),
    );
  }

  /**
   * Gets a unique identifier for this Twig extension.
   *
   * @return string
   *   A unique identifier for this Twig extension.
   */
  public function getName() {
    return 'site_reviews.twig_extension';
  }

  /**
   * Формирует форму отправки отзыва.
   */
  public static function getReviewForm($name = TRUE, $link = TRUE, $submit_label = 'Send') {
    $form = \Drupal::formBuilder()->getForm('Drupal\site_reviews\Form\SiteReviewsCreateForm', $name, $link, $submit_label);
    return \Drupal::service('renderer')->render($form, FALSE);
  }

  /**
   * Формирует отзыв по номеру с конца.
   */
  public static function getReview($id = 0) {
    $db = \Drupal::database();
    $query = $db->select('node_field_data', 'n');
    $query->condition('n.status', 1);
    $query->condition('n.type', 'review');
    $query->fields('n', array('nid'));
    $query->orderBy('n.created', 'DESC');
    $query->range($id, 1);
    $nid = $query->execute()->fetchField();

    $data = [];
    if (!empty($nid)) {
      $node = Node::load($nid);

      $viewmode = 'default';
      $entityType = 'node';
      $display = entity_get_display($entityType, 'review', $viewmode);
      $viewBuilder = \Drupal::entityTypeManager()->getViewBuilder($entityType);

      $fieldsToRender = array(
        'field_name', 'body',
      );

      $data['node'] = $node;

      foreach ($fieldsToRender as $field_name) {
        if (isset($node->{$field_name}) && $field = $node->{$field_name}) {
          $fieldRenderable = $viewBuilder->viewField($field, $display->getComponent($field_name));
          if (count($fieldRenderable) && !empty($fieldRenderable)) {
            $data[$field_name] = drupal_render($fieldRenderable);
          }
        }
      }
    }

    return $data;
  }

  /**
   * Возвращает строковое значение статуса товара на складе.
   */
  public static function getReviewsListByStaffer($node) {
    return SiteReviewsController::siteReviewsListByStaffer($node);
  }
}
