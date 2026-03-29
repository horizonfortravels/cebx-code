<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SmtpSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class InternalSmtpSettingsController extends Controller
{
    public function __construct(private SmtpSettingsService $smtpSettings)
    {
    }

    public function edit(): View
    {
        return view('pages.admin.smtp-settings', [
            'settings' => $this->smtpSettings->getSettings(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('enabled');

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'host' => [Rule::requiredIf($enabled), 'nullable', 'string', 'max:255'],
            'port' => [Rule::requiredIf($enabled), 'nullable', 'integer', 'between:1,65535'],
            'encryption' => [Rule::requiredIf($enabled), 'nullable', Rule::in(['tls', 'ssl', 'none'])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'from_name' => [Rule::requiredIf($enabled), 'nullable', 'string', 'max:255'],
            'from_address' => [Rule::requiredIf($enabled), 'nullable', 'email:rfc', 'max:255'],
            'reply_to_name' => ['nullable', 'string', 'max:255', 'required_with:reply_to_address'],
            'reply_to_address' => ['nullable', 'email:rfc', 'max:255'],
            'timeout' => ['nullable', 'integer', 'between:1,120'],
        ]);

        $this->smtpSettings->updateSettings($data, (string) $request->user()->id);

        return redirect()
            ->route('internal.smtp-settings.edit')
            ->with('success', 'تم حفظ إعدادات SMTP الداخلية بنجاح.');
    }

    public function testConnection(): RedirectResponse
    {
        try {
            $this->smtpSettings->testStoredConnection();
        } catch (Throwable $exception) {
            return redirect()
                ->route('internal.smtp-settings.edit')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('internal.smtp-settings.edit')
            ->with('success', 'تم اختبار الاتصال بخادم SMTP بنجاح.');
    }

    public function sendTestEmail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'destination' => ['required', 'email:rfc', 'max:255'],
        ]);

        try {
            $this->smtpSettings->sendStoredTestEmail($data['destination'], (string) ($request->user()->name ?? ''));
        } catch (Throwable $exception) {
            return redirect()
                ->route('internal.smtp-settings.edit')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('internal.smtp-settings.edit')
            ->with('success', 'تم إرسال بريد الاختبار بنجاح.');
    }
}
