<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * NotificationTemplate — FR-NTF-002/004/006
 *
 * Customizable notification templates per account, event, channel, and language.
 */
class NotificationTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'account_id', 'event_type', 'channel', 'language',
        'subject', 'body', 'body_html', 'sender_name', 'sender_email',
        'variables', 'is_active', 'is_system', 'version',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    /**
     * Resolve the best template: account-specific → system default.
     */
    public static function resolve(string $eventType, string $channel, string $language, ?string $accountId = null): ?self
    {
        // Try account-specific first
        if ($accountId) {
            $template = self::where('account_id', $accountId)
                ->where('event_type', $eventType)
                ->where('channel', $channel)
                ->where('language', $language)
                ->where('is_active', true)
                ->first();

            if ($template) return $template;
        }

        // Fall back to system default
        return self::whereNull('account_id')
            ->where('event_type', $eventType)
            ->where('channel', $channel)
            ->where('language', $language)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Render the template with variable substitution.
     */
    public function render(array $data): array
    {
        $subject = $this->subject;
        $body = $this->body;
        $bodyHtml = $this->body_html;

        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $placeholder = "{{" . $key . "}}";
                $subject = str_replace($placeholder, (string)$value, $subject ?? '');
                $body = str_replace($placeholder, (string)$value, $body);
                if ($bodyHtml) $bodyHtml = str_replace($placeholder, e((string)$value), $bodyHtml);
            }
        }

        return [
            'subject'   => $subject,
            'body'      => $body,
            'body_html' => $bodyHtml,
        ];
    }

    public function scopeForAccount($query, ?string $accountId)
    {
        if ($accountId) {
            return $query->where(function ($q) use ($accountId) {
                $q->where('account_id', $accountId)->orWhereNull('account_id');
            });
        }
        return $query->whereNull('account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
