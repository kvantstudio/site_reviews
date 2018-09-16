<?php

namespace Drupal\site_reviews\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use \Drupal\node\Entity\Node;
use Drupal\Core\Database\Database;

/**
 * Send reviews create form.
 */
class SiteReviewsCreateForm extends FormBase {
  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'site_reviews_create_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $submit_label = 'Send') {
    $config = $this->config('kvantstudio.settings');

    // Интерфейс отображения формы.
    $entity_type = 'node';
    $bundle = 'review';
    $form_mode = 'default';
    $entity_form_display = \Drupal::entityTypeManager()->getStorage('entity_form_display')->load($entity_type . '.' . $bundle . '.' . $form_mode);

    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);

    // Поле имя.
    $label = 'Your name';
    if (isset($field_definitions['field_name'])) {
      $instance = $field_definitions['field_name'];
      $label = (string) $instance->getLabel();
    }
    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t($label),
      '#required' => TRUE,
      '#attributes' => array('class' => array('site-reviews-create-form__name'), 'placeholder' => ''),
      '#access' => $entity_form_display->getComponent('field_name') ? TRUE : FALSE,
      '#suffix' => '<div class="site-reviews-create-form__validation-message site-reviews-create-form__validation-message-name"></div>',
    );

    // Поле ссылка на социальную сеть.
    $label = 'Link your account on a social network';
    if (isset($field_definitions['field_social_network_account'])) {
      $instance = $field_definitions['field_social_network_account'];
      $label = (string) $instance->getLabel();
    }
    $form['link'] = array(
      '#type' => 'textfield',
      '#title' => $this->t($label),
      '#required' => FALSE,
      '#attributes' => array('class' => array('site-reviews-create-form__link'), 'placeholder' => ''),
      '#access' => $entity_form_display->getComponent('field_social_network_account') ? TRUE : FALSE,
      '#ajax' => [
        'callback' => '::validateLink',
        'event' => 'change',
        'progress' => array(
          'message' => NULL,
        ),
      ],
      '#suffix' => '<div class="site-reviews-create-form__validation-message site-reviews-create-form__validation-message-link"></div>',
    );

    // Поле ссылка на сотрудника.
    $label = 'Select staffer';
    if (isset($field_definitions['field_staff'])) {
      $instance = $field_definitions['field_staff'];
      $label = (string) $instance->getLabel();
    }
    $staff = [0 => $this->t('General feedback on our work')];
    $staff += getStaffMembers();
    $form['staffer'] = [
      '#type' => 'select',
      '#title' => $this->t($label),
      '#options' => $staff,
      '#access' => count($staff) > 1 && $entity_form_display->getComponent('field_staff') ? TRUE : FALSE,
    ];

    // Поле текст сообщения.
    $label = 'Your review';
    if (isset($field_definitions['body'])) {
      $instance = $field_definitions['body'];
      $label = (string) $instance->getLabel();
    }
    $form['text'] = array(
      '#type' => 'textarea',
      '#title' => $this->t($label),
      '#description' => '',
      '#required' => TRUE, // TODO: Доработать. Нужно брать из настроек поля.
      '#attributes' => array('class' => array('site-reviews-create-form__text'), 'placeholder' => ''),
      '#rows' => 6,
      '#suffix' => '<div class="site-reviews-create-form__validation-message site-reviews-create-form__validation-message-text"></div>',
      '#access' => $entity_form_display->getComponent('body') ? TRUE : FALSE,
    );

    // Соглашение об обработке персональных данных.
    if ($data_policy_information = $config->get('text_data_policy')) {
      $form['data_policy'] = array(
        '#type' => 'checkbox',
        '#attributes' => array('class' => array('site-reviews-create-form__data-policy')),
        '#default_value' => 1,
        '#prefix' => '<div class="site-reviews-create-form__data-policy-block">',
        '#ajax' => [
          'callback' => '::validateDataPolicy',
          'event' => 'change',
          'progress' => array(
            'message' => NULL,
          ),
        ],
      );

      $data_policy_information = str_replace("@submit_label", $this->t($submit_label), $data_policy_information);
      $nid = $config->get('node_agreement_personal_data');
      $data_policy_information = str_replace("@data_policy_url", "/node/" . $nid, $data_policy_information);
      $form['data_policy_information'] = array(
        '#markup' => $data_policy_information,
        '#suffix' => '</div>',
      );
    }

    // Добавляем нашу кнопку для отправки.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t($submit_label),
      '#button_type' => 'primary',
      '#attributes' => array('class' => array('site-reviews-create-form__submit')),
      '#ajax' => [
        'callback' => '::ajaxSubmitCallback',
        'progress' => array(),
      ],
      '#suffix' => '<div class="site-reviews-create-form__submit-message"></div>',
    );

    $form['#attached']['library'][] = 'site_reviews/module';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function validateLink(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $link = (string) trim($form_state->getValue('link'));
    if ($link && !UrlHelper::isValid($link, TRUE)) {
      $response->addCommand(new HtmlCommand('.site-reviews-create-form__validation-message-link', '<i class="fas fa-exclamation-triangle"></i> ' . $this->t('The social network link is incorrect.')));
      $response->addCommand(new InvokeCommand('.site-reviews-create-form__submit', 'attr', array(['disabled' => true])));
      $response->addCommand(new InvokeCommand('.site-reviews-create-form__validation-message-link', 'show', []));
    }
    if ($link && UrlHelper::isValid($link, TRUE)) {
      $response->addCommand(new HtmlCommand('.site-reviews-create-form__validation-message-link', ''));
      $response->addCommand(new InvokeCommand('.site-reviews-create-form__submit', 'attr', array(['disabled' => false])));
      $response->addCommand(new InvokeCommand('.site-reviews-create-form__validation-message-link', 'hide', []));
    }
    if (!$link) {
      $response->addCommand(new HtmlCommand('.site-reviews-create-form__validation-message-link', ''));
      $response->addCommand(new InvokeCommand('.site-reviews-create-form__submit', 'attr', array(['disabled' => false])));
      $response->addCommand(new InvokeCommand('.site-reviews-create-form__validation-message-link', 'hide', []));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateDataPolicy(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $data_policy = $form_state->getValue('data_policy');
    if (!$data_policy) {
      $response->addCommand(new AlertCommand($this->t('Without the consent of the data processing, we can not accept a review.')));
      $response->addCommand(new InvokeCommand('.site-reviews-create-form__submit', 'attr', array(['disabled' => true])));
    } else {
      $response->addCommand(new InvokeCommand('.site-reviews-create-form__submit', 'attr', array(['disabled' => false])));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $account = \Drupal::currentUser();
    $connection = \Drupal::database();

    // Выполняет стандартную валидацию полей формы и добавляет примечания об ошибках.
    FormBase::validateForm($form, $form_state);

    // Поле имя.
    $name = (string) trim($form_state->getValue('name'));
    if (!$name) {
      $response->addCommand(new HtmlCommand('.site-reviews-create-form__validation-message-name', '<i class="fa fa-exclamation-circle" aria-hidden="true"></i> ' . $this->t('It should be filled.')));
      $response->addCommand(new InvokeCommand('.site-reviews-create-form__validation-message-name', 'show', []));
    } else {
      $response->addCommand(new HtmlCommand('.site-reviews-create-form__validation-message-name', ''));
      $response->addCommand(new InvokeCommand('.site-reviews-create-form__validation-message-name', 'hide', []));
    }

    // Поле ссылка на сотрудника организации.
    $staffer = (int) $form_state->getValue('staffer');

    // Поле текст отзыва.
    $text = (string) trim($form_state->getValue('text'));
    if (!$text) {
      $response->addCommand(new HtmlCommand('.site-reviews-create-form__validation-message-text', '<i class="fa fa-exclamation-circle" aria-hidden="true"></i> ' . $this->t('It should be filled.')));
      $response->addCommand(new InvokeCommand('.site-reviews-create-form__validation-message-text', 'show', []));
    } else {
      $response->addCommand(new HtmlCommand('.site-reviews-create-form__validation-message-text', ''));
      $response->addCommand(new InvokeCommand('.site-reviews-create-form__validation-message-text', 'hide', []));
    }

    // Поле ссылка на аккаунт в социальной сети.
    $link = (string) trim($form_state->getValue('link'));

    // Сохранение значений формы.
    if (!drupal_get_messages() && !$form_state->getValue('validate_error')) {
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $node = Node::create([
        'type' => 'review',
        'langcode' => $language,
        'uid' => $account->id(),
        'status' => 0,
        'title' => $this->t('Review customer – @name', array('@name' => $name)),
        'field_name' => $name,
        'field_staff' => $staffer,
        'body' => [
          'summary' => Unicode::substr($text, 0, 100),
          'value' => $text,
          'format' => 'basic_html',
        ],
        'field_social_network_account' => [
          'uri' => $link,
          'title' => $link,
        ],
      ]);
      $node->save();

      // Массив данных для отправки уведомления на почту.
      $fields = [
        'uid' => $account->id(),
        'form_name' => $this->getFormId(),
        'name' => $name,
        'phone' => '',
        'mail' => '',
        'text' => $text,
        'status' => 0,
        'created' => REQUEST_TIME,
      ];

      // Регистрация сообщения в БД.
      $fields['mid'] = $connection->insert('site_send_message', ['return' => Database::RETURN_INSERT_ID])
        ->fields($fields)
        ->execute();

      // Проверяем нужно ли отправить сообщение мгновенно.
      if (is_numeric($fields['mid'])) {
        site_send_message_to_email($fields);
      }

      // Редирект на страницу уведомления об отправке отзыва.
      $route_name = 'site_reviews.feedback_sent_successfully';
      $response->addCommand(new RedirectCommand($this->url($route_name, ['name' => $name])));
    }

    return $response;
  }
}