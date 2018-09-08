<?php

/**
 * @file
 * Contains \Drupal\site_reviews\Controller\SiteReviewsController
 */

namespace Drupal\site_reviews\Controller;

use Drupal\Core\Controller\ControllerBase;

class SiteReviewsController extends ControllerBase {

  /**
   * Страница с перечнем отзывов.
   */
  public static function siteReviewsList($limit = 0) {
    $build = [];

    $db = \Drupal::database();
    $query = $db->select('node_field_data', 'n');
    $query->condition('n.status', 1);
    $query->condition('n.type', 'review');
    $query->fields('n', ['nid']);
    $query->orderBy('n.created', 'DESC');

    if ($limit) {
      $query->range(0, $limit);
      $result = $query->execute();
    } else {
      $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(100);
      $result = $pager->execute();
    }

    $nids = [];
    foreach ($result as $row) {
      $nids[] = $row->nid;
    }

    $nodes = [];
    if (!empty($nids)) {
      $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    }

    // Создает HTML отображение материалов.
    $build['site_reviews_list'] = [
      '#theme' => 'site_reviews_list',
      '#nodes' => $nodes,
      '#attached' => [
        'library' => [
          'site_reviews/module',
        ],
      ],
    ];

    // Добавляет пейджер на страницу.
    if (!$limit) {
      $build['pager'] = [
        '#type' => 'pager',
      ];
    }

    return $build;
  }

  /**
   * Страница с перечнем отзывов по выбранному сотруднику.
   */
  public static function siteReviewsListByStaffer($node) {
    $build = [];

    $db = \Drupal::database();
    $query = $db->select('node_field_data', 'n');
    $query->condition('n.status', 1);
    $query->condition('n.type', 'review');
    $query->fields('n', ['nid']);
    $query->join('node__field_staff', 's', 's.entity_id = n.nid');
    $query->condition('s.field_staff_target_id', $node->id());
    $query->orderBy('n.created', 'DESC');
    $nids = $query->execute()->fetchAssoc();

    $nodes = [];
    if (!empty($nids)) {
      $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
    }

    // Создает HTML отображение материалов.
    $build['site_reviews_list'] = [
      '#theme' => 'site_reviews_list_by_staffer',
      '#node' => $node,
      '#nodes' => $nodes,
      '#attached' => [
        'library' => [
          'site_reviews/module',
        ],
      ],
    ];

    return $build;
  }

  /**
   * Страница уведомления об успешной отправке отзыва.
   */
  public function siteReviewsFeedbackSentSuccessfully($name = NULL) {
    // Запрещаем индексирование в поисковых системах.
    $noindex_meta_tag = [
      '#tag' => 'meta',
      '#attributes' => [
        'name' => 'robots',
        'content' => 'noindex, nofollow',
      ],
    ];

    return [
      '#theme' => 'site_reviews_feedback_sent_successfully',
      '#name' => $name,
      '#attached' => [
        'library' => [
          'site_reviews/module',
        ],
        'html_head' => [[$noindex_meta_tag, 'noindex']],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }
}