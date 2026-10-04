<?php

namespace App\GodMode;

use App\GodMode\Editors\CompanyAddressEditor;
use App\GodMode\Editors\CompanyContactEditor;
use App\GodMode\Editors\CompanyEditor;
use App\GodMode\Editors\CompanyGstinEditor;
use App\GodMode\Editors\DealBillingEditor;
use App\GodMode\Editors\DealConditionEditor;
use App\GodMode\Editors\DealDocumentEditor;
use App\GodMode\Editors\DealExecutionEditor;
use App\GodMode\Editors\DealIsinEditor;
use App\GodMode\Editors\DealSecurityEditor;
use App\GodMode\Editors\DealStatusEditor;
use App\GodMode\Editors\DiligenceItemEditor;
use App\GodMode\Editors\FeesEditor;
use App\GodMode\Editors\IsinPaymentEditor;
use App\GodMode\Editors\IssueDetailsEditor;
use App\GodMode\Editors\JobSheetEntryEditor;
use App\GodMode\Editors\SecurityRegistrationEditor;
use App\GodMode\Editors\TransactionBasicsEditor;
use App\GodMode\Editors\TransactionContactsEditor;
use Illuminate\Database\Eloquent\Model;

/** Every record type God Mode can correct, by key. */
class Editors
{
    /** @var list<class-string<Editor>> */
    private const EDITORS = [
        CompanyEditor::class,
        CompanyGstinEditor::class,
        CompanyAddressEditor::class,
        CompanyContactEditor::class,
        TransactionBasicsEditor::class,
        TransactionContactsEditor::class,
        IssueDetailsEditor::class,
        FeesEditor::class,
        DealBillingEditor::class,
        DealStatusEditor::class,
        JobSheetEntryEditor::class,
        DealDocumentEditor::class,
        DealConditionEditor::class,
        DealExecutionEditor::class,
        DealSecurityEditor::class,
        SecurityRegistrationEditor::class,
        DiligenceItemEditor::class,
        DealIsinEditor::class,
        IsinPaymentEditor::class,
    ];

    /** Corrections after which the engagement letter may no longer match the data. */
    public const AFFECT_LETTER = ['company', 'company-address', 'company-contact', 'transaction-basics', 'transaction-contacts', 'issue-details', 'fees'];

    public static function get(string $key): Editor
    {
        foreach (self::EDITORS as $class) {
            $editor = app($class);
            if ($editor->key() === $key) {
                return $editor;
            }
        }

        abort(404);
    }

    /**
     * The editable form of one record for the God Mode screens.
     *
     * @return array<string, mixed>
     */
    public static function present(Editor $editor, Model $record, string $id): array
    {
        return [
            'editor' => $editor->key(),
            'label' => $editor->label(),
            'id' => $id,
            'values' => $editor->values($record),
            'fields' => $editor->fields($record),
            'fingerprint' => $editor->fingerprint($record),
            'summary' => $editor->showsSummary(),
        ];
    }
}
