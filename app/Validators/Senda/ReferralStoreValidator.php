<?php

declare(strict_types=1);

namespace App\Validators\Senda;

use App\Services\Senda\AssistedReferralCatalog;
use App\Services\Senda\DemandOrigin;
use Core\Validator;

final class ReferralStoreValidator
{
    public function validate(array $data): array
    {
        $origins = implode(',', DemandOrigin::values());
        $requestTypes = implode(',', array_column(AssistedReferralCatalog::requestTypes(), 'value'));
        $kinds = implode(',', array_column(AssistedReferralCatalog::applicantKinds(), 'value'));
        $genders = implode(',', array_column(AssistedReferralCatalog::genders(), 'value'));
        $risks = implode(',', array_column(AssistedReferralCatalog::riskLevels(), 'value'));
        $frequencies = implode(',', array_column(AssistedReferralCatalog::frequencies(), 'value'));
        $modalities = implode(',', array_column(AssistedReferralCatalog::treatmentModalities(), 'value'));
        $stays = implode(',', array_column(AssistedReferralCatalog::treatmentStayPeriods(), 'value'));
        $destinationCenters = implode(',', array_column(AssistedReferralCatalog::destinationCenters(), 'value'));
        $kind = trim((string) ($data['applicant_kind'] ?? ''));
        $hasPreviousTreatments = trim((string) ($data['has_previous_treatments'] ?? ''));
        $rules = [
            'senda_attention_id' => 'required|integer',
            'request_date' => 'required|date',
            'demand_origin' => 'required|in:' . $origins,
            'receiving_officer' => 'required|min:3|max:180',
            'demand_area' => 'nullable|max:120',
            'request_type' => 'nullable|in:' . $requestTypes,
            'requesting_device' => 'nullable|max:180',
            'requesting_commune' => 'nullable|max:120',
            'destination_center_select' => 'nullable|in:' . $destinationCenters,
            'destination_center_other' => 'nullable|max:180',
            'destination_commune' => 'nullable|max:120',
            'applicant_kind' => 'required|in:' . $kinds,
            'applicant_phone' => 'nullable|max:30',
            'applicant_email' => 'nullable|email|max:150',
            'gender' => 'nullable|in:' . $genders,
            'health_insurance' => 'nullable|max:80',
            'nationality' => 'nullable|max:80',
            'indigenous_people' => 'nullable|max:80',
            'enrolled_health_center' => 'required|in:si,no',
            'emergency_contact_name' => 'nullable|max:180',
            'emergency_contact_phone' => 'nullable|max:30',
            'age_of_onset' => 'nullable|max:40',
            'consumption_frequency' => 'nullable|in:' . $frequencies,
            'mental_health_history' => 'nullable|max:2000',
            'physical_health_history' => 'nullable|max:2000',
            'family_situation' => 'nullable|max:2000',
            'legal_situation' => 'nullable|max:2000',
            'support_network' => 'nullable|max:2000',
            'has_previous_treatments' => 'required|in:si,no',
            'suicide_risk' => 'nullable|in:' . $risks,
            'violence_risk' => 'nullable|in:' . $risks,
            'overall_risk' => 'nullable|in:' . $risks,
            'risk_notes' => 'nullable|max:2000',
            'screening_used' => 'required|in:si,no',
            'observations' => 'nullable|max:4000',
        ];

        if ($kind === 'familiar') {
            $rules['applicant_name'] = 'required|min:3|max:180';
            $relationships = implode(',', array_column(AssistedReferralCatalog::familyApplicantRelationships(), 'value'));
            $rules['applicant_relationship'] = 'required|in:' . $relationships;
        } elseif ($kind === 'institucional') {
            $rules['applicant_name'] = 'required|min:3|max:180';
            $relationships = implode(',', array_column(AssistedReferralCatalog::institutionalApplicantRelationships(), 'value'));
            $rules['applicant_relationship'] = 'required|in:' . $relationships;
        }

        if (trim((string) ($data['enrolled_health_center'] ?? '')) === 'si') {
            $rules['cesfam_name'] = 'required|min:2|max:180';
        }

        if (trim((string) ($data['destination_center_select'] ?? '')) === 'otros') {
            $rules['destination_center_other'] = 'required|min:2|max:180';
        }

        if ($hasPreviousTreatments === 'si') {
            $rules['previous_treatments_count'] = 'required|integer';
            $rules['previous_treatment_modality'] = 'required|in:' . $modalities;
            $rules['previous_treatment_stay'] = 'required|in:' . $stays;
            $rules['previous_treatment_completed'] = 'required|in:si,no';
            $rules['previous_treatment_center'] = 'required|min:2|max:180';
            $rules['previous_treatment_commune'] = 'required|min:2|max:120';
            $rules['previous_treatments_detail'] = 'nullable|max:2000';
        }

        $validator = new Validator();
        $validator->validate($data, $rules, [
            'senda_attention_id' => 'atención',
            'request_date' => 'fecha',
            'demand_origin' => 'origen de demanda',
            'receiving_officer' => 'funcionario que acoge la demanda',
            'demand_area' => 'área',
            'request_type' => 'tipo de solicitud',
            'requesting_device' => 'dispositivo que solicita',
            'requesting_commune' => 'comuna de origen',
            'destination_center_select' => 'centro o dispositivo de destino',
            'destination_center_other' => 'otro centro o dispositivo de destino',
            'destination_commune' => 'comuna de destino',
            'applicant_kind' => 'quién realiza la solicitud',
            'applicant_name' => 'nombre completo',
            'applicant_phone' => 'teléfono',
            'applicant_email' => 'correo electrónico',
            'applicant_relationship' => 'tipo de relación',
            'gender' => 'sexo',
            'health_insurance' => 'previsión',
            'nationality' => 'nacionalidad',
            'indigenous_people' => 'pueblo originario',
            'enrolled_health_center' => 'inscrita en centro de salud',
            'cesfam_name' => 'nombre del CESFAM',
            'emergency_contact_name' => 'contacto de emergencia',
            'emergency_contact_phone' => 'teléfono de emergencia',
            'age_of_onset' => 'edad de inicio',
            'consumption_frequency' => 'frecuencia de consumo',
            'mental_health_history' => 'salud mental',
            'physical_health_history' => 'salud física',
            'family_situation' => 'situación familiar',
            'legal_situation' => 'situación judicial',
            'support_network' => 'red de apoyo',
            'has_previous_treatments' => 'tratamientos previos en sistema SENDA',
            'previous_treatments_count' => 'cantidad de tratamientos previos',
            'previous_treatment_modality' => 'modalidad',
            'previous_treatment_stay' => 'tiempo de permanencia',
            'previous_treatment_completed' => 'término de tratamiento',
            'previous_treatment_center' => 'nombre del centro',
            'previous_treatment_commune' => 'comuna',
            'previous_treatments_detail' => 'observación de tratamientos previos',
            'suicide_risk' => 'riesgo suicida',
            'violence_risk' => 'riesgo de violencia',
            'overall_risk' => 'nivel de riesgo',
            'risk_notes' => 'notas de riesgo',
            'screening_used' => 'uso de instrumento de tamizaje',
            'observations' => 'observaciones',
        ]);

        $errors = $validator->firstErrors();

        if (isset($data['substance_keys']) && is_array($data['substance_keys'])) {
            foreach ($data['substance_keys'] as $key) {
                if (!AssistedReferralCatalog::isValidConsumptionSubstance(trim((string) $key))) {
                    $errors['substance_keys'] = 'Seleccione sustancias válidas en antecedentes de consumo.';
                    break;
                }
            }
        }

        if ($hasPreviousTreatments === 'si' && !isset($errors['previous_treatments_count'])) {
            $count = (int) ($data['previous_treatments_count'] ?? 0);
            if ($count < 1) {
                $errors['previous_treatments_count'] = 'El campo cantidad de tratamientos previos debe ser al menos 1.';
            }
        }

        if (trim((string) ($data['screening_used'] ?? '')) === 'si') {
            $assist = is_array($data['assist'] ?? null) ? $data['assist'] : [];

            foreach (AssistedReferralCatalog::assistSubstances() as $substance) {
                $key = $substance['key'];
                $score = trim((string) ($assist[$key]['score'] ?? ''));
                if ($score === '') {
                    continue;
                }

                if (!ctype_digit($score) || (int) $score > 39) {
                    $errors['assist.' . $key . '.score'] = 'El puntaje ASSIST de ' . $substance['label'] . ' debe ser un número entre 0 y 39.';
                }
            }
        }

        return $errors;
    }
}
