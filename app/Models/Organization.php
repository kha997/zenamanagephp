<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Organization extends Model
{

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'description',
        'logo_url',
        'website',
        'phone',
        'address',
        'timezone',
        'currency',
        'language',
        'allow_self_registration',
        'require_email_verification',
        'require_admin_approval',
        'allowed_domains',
        'settings',
        'status',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    protected $casts = [
        'allowed_domains' => 'array',
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'allow_self_registration' => 'boolean',
        'require_email_verification' => 'boolean',
        'require_admin_approval' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($organization) {
            if (empty($organization->slug)) {
                $organization->slug = Str::slug($organization->name);
            }
        });
    }

    // Relationships
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function owner()
    {
        return $this->hasOne(User::class)->where('role', 'super_admin');
    }

    public function admins()
    {
        return $this->hasMany(User::class)->whereIn('role', ['super_admin', 'admin']);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByDomain($query, $domain)
    {
        return $query->where('domain', $domain);
    }

    // Accessors & Mutators
    public function getFullNameAttribute()
    {
        return $this->name;
    }

    public function getIsTrialActiveAttribute()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function getIsSubscriptionActiveAttribute()
    {
        return $this->subscription_ends_at && $this->subscription_ends_at->isFuture();
    }

    // Methods
    public function isDomainAllowed($email)
    {
        if (empty($this->allowed_domains)) {
            return true;
        }

        $domain = substr(strrchr($email, "@"), 1);
        return in_array($domain, $this->allowed_domains);
    }

    public function canSelfRegister($email)
    {
        return $this->allow_self_registration && $this->isDomainAllowed($email);
    }

    public function getInvitationSettings()
    {
        return [
            'expires_in_days' => $this->settings['invitation_expires_in_days'] ?? 7,
            'require_email_verification' => $this->require_email_verification,
            'require_admin_approval' => $this->require_admin_approval,
        ];
    }

    public function getAvailableRoles()
    {
        return [
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'project_manager' => 'Project Manager',
            'designer' => 'Designer',
            'site_engineer' => 'Site Engineer',
            'qc_engineer' => 'QC Engineer',
            'procurement' => 'Procurement',
            'finance' => 'Finance',
            'client' => 'Client',
            'user' => 'User',
        ];
    }
}