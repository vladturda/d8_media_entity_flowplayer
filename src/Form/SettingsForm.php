<?php

namespace Drupal\d8_media_entity_flowplayer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Administration form.
 */
class SettingsForm extends ConfigFormBase
{
    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'd8_media_entity_flowplayer_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames() {
        return [
            'd8_media_entity_flowplayer.settings'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm( array $form, FormStateInterface $form_state, $field_type = null )
    {
        /** @var \Drupal\Core\Config\Config $config */
        $config = $this->config( 'd8_media_entity_flowplayer.settings' );

        $form['api'] = [
            '#type' => 'details',
            '#title' => $this->t( 'Flowplayer API Key' ),
            '#description' => $this->t( 'The API Key can be requested at the <a href=":url" target="_blank">Developers & API page</a>.', [':url' => 'https://www.Flowplayer.net/developers/applyforapi'] ),
            '#open' => true,
        ];

        $form['api']['api_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t( 'API Key' ),
            '#default_value' => $config->get( 'api_key' ),
            '#description' => $this->t( 'Set this to the API Key that Flowplayer has provided for you.' ),
            '#required' => true,
        ];

        $form['api']['site_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t( 'Site ID' ),
            '#default_value' => $config->get( 'site_id' ),
            '#description' => $this->t( 'Set this to the Site ID that Flowplayer has provided for you.' ),
            '#required' => true,
        ];

        return parent::buildForm( $form, $form_state );
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm( array &$form, FormStateInterface $form_state )
    {
        // @todo: Check values with API.
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm( array &$form, FormStateInterface $form_state )
    {
        /** @var \Drupal\Core\Config\Config $config */
        $config = $this->config( 'd8_media_entity_flowplayer.settings' );
        $keys = [ 'api_key', 'site_id' ];

        foreach ($keys as $key) {
            $config->set( $key, $form_state->getValue( $key ) );
        }

        $config->save();
    }

}
