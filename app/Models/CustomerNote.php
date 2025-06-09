<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'type',
        'subject',
        'content',
        'priority',
        'is_private',
        'requires_follow_up',
        'follow_up_date',
        'follow_up_assigned_to',
        'status',
        'attachments',
        'tags',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'requires_follow_up' => 'boolean',
        'follow_up_date' => 'datetime',
        'attachments' => 'array',
        'tags' => 'array',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function followUpAssignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follow_up_assigned_to');
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    public function scopeRequiringFollowUp($query)
    {
        return $query->where('requires_follow_up', true)
            ->where('status', '!=', 'completed');
    }

    public function scopeOverdueFollowUp($query)
    {
        return $query->where('requires_follow_up', true)
            ->where('follow_up_date', '<', now())
            ->where('status', '!=', 'completed');
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('follow_up_assigned_to', $userId);
    }

    public function scopeCreatedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeWithTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Accessors
    public function getDisplayTitleAttribute(): string
    {
        if ($this->subject) {
            return $this->subject;
        }

        return ucfirst($this->type) . " - " . $this->created_at->format('M j, Y');
    }

    public function getShortContentAttribute(): string
    {
        return strlen($this->content) > 100
            ? substr($this->content, 0, 100) . '...'
            : $this->content;
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'red',
            'high' => 'orange',
            'normal' => 'blue',
            'low' => 'gray',
            default => 'blue'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'open' => 'blue',
            'in_progress' => 'yellow',
            'completed' => 'green',
            'cancelled' => 'gray',
            default => 'blue'
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->requires_follow_up
            && $this->follow_up_date
            && $this->follow_up_date->isPast()
            && $this->status !== 'completed';
    }

    public function getDaysUntilFollowUpAttribute(): ?int
    {
        if (!$this->requires_follow_up || !$this->follow_up_date) {
            return null;
        }

        return now()->diffInDays($this->follow_up_date, false);
    }

    public function getAttachmentCountAttribute(): int
    {
        return $this->attachments ? count($this->attachments) : 0;
    }

    public function getTagsListAttribute(): string
    {
        return $this->tags ? implode(', ', $this->tags) : '';
    }

    // Business Logic Methods
    public function markAsCompleted(): bool
    {
        return $this->update(['status' => 'completed']);
    }

    public function markAsInProgress(): bool
    {
        return $this->update(['status' => 'in_progress']);
    }

    public function markAsCancelled(): bool
    {
        return $this->update(['status' => 'cancelled']);
    }

    public function assignFollowUp(int $userId, \DateTime $followUpDate): bool
    {
        return $this->update([
            'requires_follow_up' => true,
            'follow_up_assigned_to' => $userId,
            'follow_up_date' => $followUpDate,
            'status' => 'open'
        ]);
    }

    public function removeFollowUp(): bool
    {
        return $this->update([
            'requires_follow_up' => false,
            'follow_up_assigned_to' => null,
            'follow_up_date' => null
        ]);
    }

    public function addTag(string $tag): bool
    {
        $tags = $this->tags ?: [];

        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            return $this->update(['tags' => $tags]);
        }

        return true;
    }

    public function removeTag(string $tag): bool
    {
        $tags = $this->tags ?: [];
        $tags = array_filter($tags, fn($t) => $t !== $tag);

        return $this->update(['tags' => array_values($tags)]);
    }

    public function addAttachment(string $filePath, string $originalName): bool
    {
        $attachments = $this->attachments ?: [];

        $attachments[] = [
            'path' => $filePath,
            'name' => $originalName,
            'uploaded_at' => now()->toISOString()
        ];

        return $this->update(['attachments' => $attachments]);
    }

    public function removeAttachment(string $filePath): bool
    {
        $attachments = $this->attachments ?: [];
        $attachments = array_filter($attachments, fn($a) => $a['path'] !== $filePath);

        return $this->update(['attachments' => array_values($attachments)]);
    }

    public function canBeViewedBy(User $user): bool
    {
        // Private notes can only be viewed by the creator
        if ($this->is_private && $this->user_id !== $user->id) {
            return false;
        }

        // Check if user has permission to view customer notes
        return $user->can('view customer notes');
    }

    public function canBeEditedBy(User $user): bool
    {
        // Only the creator can edit private notes
        if ($this->is_private && $this->user_id !== $user->id) {
            return false;
        }

        // Check if user has permission to edit customer notes
        return $user->can('edit customer notes');
    }

    public function getTypeDisplayNameAttribute(): string
    {
        return match ($this->type) {
            'general' => 'General Note',
            'call' => 'Phone Call',
            'meeting' => 'Meeting',
            'email' => 'Email',
            'complaint' => 'Complaint',
            'follow_up' => 'Follow Up',
            'payment' => 'Payment Issue',
            'delivery' => 'Delivery Issue',
            'other' => 'Other',
            default => ucfirst($this->type)
        };
    }
}
