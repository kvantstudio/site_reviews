<?php

/**
 * @file
 * Contains \Drupal\site_reviews\Controller\SiteReviewsController
 */

namespace Drupal\site_reviews\Controller;

use Drupal\Core\Controller\ControllerBase;

class SiteReviewsController extends ControllerBase {

    /**
     * Страница отображения каталога отзывов.
     */
    public static function view($limit = 0) {
        $build = array();

        $db = \Drupal::database();
        $query = $db->select('node_field_data', 'n');
        $query->condition('n.status', 1);
        $query->condition('n.type', 'review');
        $query->fields('n', array('nid'));
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

        $nodes = NULL;
        if (!empty($nids)) {
            $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids);
        }

        // Создает HTML отображение материалов.
        $build['reviews'] = array(
            '#theme' => 'reviews',
            '#nodes' => $nodes,
            '#attached' => array(
                'library' => array(
                    'site_reviews/module',
                ),
            ),
        );

        // Добавляет пейджер на страницу.
        if (!$limit) {
            $build['pager'] = array(
                '#type' => 'pager',
            );
        }

        return $build;
    }
}