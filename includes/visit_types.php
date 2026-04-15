<?php
/**
 * Visit Type Definitions
 * Defines available visit types and their required forms.
 * Each form key must match an entry in $formDefs / form_submissions.form_type.
 */

define('VISIT_TYPES', [
    'routine' => [
        'label'    => 'Routine Visit',
        'icon'     => 'bi-house-medical-fill',
        'color'    => 'indigo',
        'required' => ['vital_cs', 'abn'],
    ],
    'new_patient' => [
        'label'    => 'New Patient',
        'icon'     => 'bi-person-plus-fill',
        'color'    => 'blue',
        'required' => ['new_patient', 'vital_cs', 'abn', 'pf_signup'],
    ],
    'wound_care' => [
        'label'    => 'Wound Care',
        'icon'     => 'bi-bandaid-fill',
        'color'    => 'red',
        'required' => ['vital_cs', 'abn'],
    ],
    'awv' => [
        'label'    => 'Annual Wellness',
        'icon'     => 'bi-clipboard2-pulse-fill',
        'color'    => 'sky',
        'required' => ['vital_cs', 'medicare_awv', 'abn'],
    ],
    'ccm' => [
        'label'    => 'CCM Visit',
        'icon'     => 'bi-calendar2-heart-fill',
        'color'    => 'emerald',
        'required' => ['vital_cs', 'ccm_consent', 'abn'],
    ],
    'il' => [
        'label'    => 'IL Disclosure',
        'icon'     => 'bi-file-earmark-text-fill',
        'color'    => 'slate',
        'required' => ['vital_cs', 'il_disclosure', 'abn'],
    ],
]);

/** Form labels for checklist display */
define('FORM_LABELS', [
    'vital_cs'           => 'Visit Consent',
    'new_patient'        => 'New Patient Consent',
    'abn'                => 'ABN (CMS-R-131)',
    'pf_signup'          => 'PF Portal Consent',
    'ccm_consent'        => 'CCM Consent',
    'cognitive_wellness' => 'Cognitive Wellness Exam',
    'medicare_awv'       => 'Medicare AWV',
    'il_disclosure'      => 'IL Disclosure Auth.',
]);
