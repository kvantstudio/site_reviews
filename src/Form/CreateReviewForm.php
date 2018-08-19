<?php

namespace Drupal\site_reviews\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use \Drupal\node\Entity\Node;
use Drupal\Component\Utility\Unicode;

/**
 * Send message form.
 */
class CreateReviewForm extends FormBase {
    /**
     * {@inheritdoc}.
     */
    public function getFormId() {
        return 'site_review_form';
    }

    /**
     * {@inheritdoc}.
     */
    public function buildForm(array $form, FormStateInterface $form_state, $name = TRUE, $link = TRUE, $submit_label = 'Send') {
        $config = $this->config('kvantstudio.settings');

        // Поле имя.
        $form['name'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Your name'),
            '#required' => TRUE,
            '#attributes' => array('class' => array('site-review-form__name'), 'placeholder' => ''),
            '#access' => $name ? TRUE : FALSE,
            '#suffix' => '<div class="site-review-form__name-validation-message"></div>',
        );

        // Поле ссылка на социальную сеть.
        $form['link'] = array(
            '#type' => 'textfield',
            '#title' => $this->t('Link your account on a social network'),
            '#required' => FALSE,
            '#attributes' => array('class' => array('site-review-form__link'), 'placeholder' => ''),
            '#access' => $link ? TRUE : FALSE,
        );

        // Поле текст сообщения.
        $form['text'] = array(
            '#type' => 'textarea',
            '#title' => $this->t('Your review'),
            '#description' => '',
            '#required' => TRUE,
            '#attributes' => array('class' => array('site-review-form__text'), 'placeholder' => ''),
            '#rows' => 6,
        );

        // Соглашение об обработке персональных данных.
        if ($data_policy_information = $config->get('text_data_policy')) {
            $form['data_policy'] = array(
                '#type' => 'checkbox',
                '#attributes' => array('class' => array('site-review-form__data-policy')),
                '#default_value' => 1,
                '#prefix' => '<div class="site-review-form__data-policy-block">',
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
            '#attributes' => array('class' => array('site-review-form__submit')),
            '#ajax' => [
                'callback' => '::ajaxSubmitCallback',
                'progress' => array(),
            ],
            '#suffix' => '<div class="site-review-form__submit-message"></div>',
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
    public function validateDataPolicy(array &$form, FormStateInterface $form_state) {
        $response = new AjaxResponse();

        $data_policy = $form_state->getValue('data_policy');
        if (!$data_policy) {
            $response->addCommand(new AlertCommand($this->t('Without the consent of the data processing, we can not accept a review.')));
            $response->addCommand(new InvokeCommand('.site-review-form__submit', 'attr', array(['disabled' => true])));
        } else {
            $response->addCommand(new InvokeCommand('.site-review-form__submit', 'attr', array(['disabled' => false])));
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

        // Поля формы регистрации. 
        $name = trim($form_state->getValue('name'));
        if (!$name) {
            $response->addCommand(new HtmlCommand('.site-review-form__name-validation-message', '<i class="fa fa-exclamation-circle" aria-hidden="true"></i> ' . $this->t('It should be filled.')));
        } else {
            $response->addCommand(new HtmlCommand('.site-review-form__name-validation-message', ''));
        }

        // Очищаем текстовое поле от тегов. 
        $text = trim($form_state->getValue('text'));
        $text = strip_tags($text);                                 

        // Сохранение значений формы регистрации. 
        if (!drupal_get_messages() && !$form_state->getValue('validate_error')) {
            // Создает node.
            $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
            $node = Node::create([
                'type' => 'review',
                'langcode' => $language,
                'uid' => 1,
                'status' => 0,
                'title' => $this->t('Review customer – @name', array('@name' => $name)),
                'field_name' => $name,
                'body' => [
                    'summary' => Unicode::substr($text, 0, 100),                    
                    'value' => $text,
                    'format' => 'basic_html',
                ],
                'field_social_network_account' => [
                    'uri' => trim($form_state->getValue('link')),
                    'title' => trim($form_state->getValue('link')),
                ],
            ]);
            $node->save();

            // Уведомление об успешной отправке формы и очистка полей.
            $response->addCommand(new InvokeCommand('.site-review-form input[type=text]', 'val', array('')));            
            $response->addCommand(new InvokeCommand('.site-review-form__text', 'val', array('')));
            $text = $this->t('@name, your review has been sent', array('@name' => trim($form_state->getValue('name'))));
            $response->addCommand(new HtmlCommand('.site-review-form__submit-message', $text));
        } else {
            $text = '<i class="fa fa-info" aria-hidden="true"></i> ' . $this->t('Fill in the correct form field.');
            $response->addCommand(new HtmlCommand('.site-review-form__submit-message', $text));
        }

        return $response;
    }
}