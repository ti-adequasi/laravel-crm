<?php

namespace Webkul\LeadGreen\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Models\LeadProxy;
use Webkul\LeadGreen\Casts\SafeJsonCast;
use Webkul\LeadGreen\Contracts\LeadGreen as LeadGreenContract;
use Webkul\User\Models\UserProxy;

class LeadGreen extends Model implements LeadGreenContract
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lead_green_prospects';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        // Business identity, from the Google Maps search result.
        'business_id',
        'name',
        'phone_number',
        'website',
        'full_address',
        'full_address_array',
        'city',
        'state',
        'latitude',
        'longitude',
        'review_count',
        'rating',
        'timezone',
        'place_id',
        'place_link',
        'types',
        'price_level',
        'working_hours',
        'is_claimed',
        'verified',
        'is_permanently_closed',
        'is_temporarily_closed',
        'photos',
        'description',

        // Prospecting funnel.
        'lead_status',
        'used_at',
        'used_by',
        'used_reason',
        'opportunity_id',

        // Website enrichment.
        'email',
        'email_source',
        'email_quality',
        'email_verified',
        'emails_found',
        'instagram',
        'facebook',
        'linkedin',
        'whatsapp',
        'enrichment_status',
        'enrichment_score',
        'enriched_at',
        'has_privacy_policy',
        'privacy_policy_url',
        'has_dpo',
        'dpo_name',
        'dpo_email',

        // CNPJ / company-registry enrichment.
        'cnpj',
        'cnpj_source',
        'razao_social',
        'nome_fantasia',
        'situacao_cadastral',
        'data_abertura',
        'cnae_code',
        'cnae_description',
        'inscricao_estadual',
        'porte',
        'natureza_juridica',
        'capital_social',
        'company_phone',
        'company_email',
        'opcao_simples',
        'opcao_mei',
        'socios',
        'company_data_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'full_address_array' => SafeJsonCast::class,
        'types' => SafeJsonCast::class,
        'working_hours' => SafeJsonCast::class,
        'photos' => SafeJsonCast::class,
        'description' => SafeJsonCast::class,
        'emails_found' => SafeJsonCast::class,
        'socios' => SafeJsonCast::class,
        'rating' => 'float',
        'review_count' => 'integer',
        'is_claimed' => 'boolean',
        'verified' => 'boolean',
        'email_verified' => 'boolean',
        'is_permanently_closed' => 'boolean',
        'is_temporarily_closed' => 'boolean',
        'used_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'enrichment_score' => 'integer',
        'enriched_at' => 'datetime',
        'has_privacy_policy' => 'boolean',
        'has_dpo' => 'boolean',
        'data_abertura' => 'date',
        'capital_social' => 'decimal:2',
        'opcao_simples' => 'boolean',
        'opcao_mei' => 'boolean',
        'company_data_at' => 'datetime',
    ];

    /**
     * Get the user that last actioned this prospect.
     */
    public function user()
    {
        return $this->belongsTo(UserProxy::modelClass(), 'used_by');
    }

    /**
     * Get the CRM lead this prospect was converted into.
     */
    public function opportunity()
    {
        return $this->belongsTo(LeadProxy::modelClass(), 'opportunity_id');
    }

    public function scopeNovo($query)
    {
        return $query->where('lead_status', 'novo');
    }

    /**
     * Prospects still workable: never touched, or explicitly reusable.
     */
    public function scopeAvailable($query)
    {
        return $query->whereIn('lead_status', ['novo', 'reaproveitavel']);
    }

    public function scopeFilterByCity($query, $city)
    {
        return $city ? $query->where('city', 'like', '%'.$city.'%') : $query;
    }

    public function scopeFilterByState($query, $state)
    {
        return $state ? $query->where('state', $state) : $query;
    }

    public function scopeFilterByStatus($query, $status)
    {
        return $status ? $query->where('lead_status', $status) : $query;
    }

    public function scopeFilterByMinRating($query, $rating)
    {
        return $rating ? $query->where('rating', '>=', $rating) : $query;
    }

    public function scopeFilterByMinReviews($query, $count)
    {
        return $count ? $query->where('review_count', '>=', $count) : $query;
    }

    public function scopeHasPhone($query, $hasPhone)
    {
        return $hasPhone !== null
            ? $query->whereNotNull('phone_number')->where('phone_number', '!=', '')
            : $query;
    }

    public function scopeHasWebsite($query, $hasWebsite)
    {
        return $hasWebsite !== null
            ? $query->whereNotNull('website')->where('website', '!=', '')
            : $query;
    }

    public function scopeFilterByType($query, $type)
    {
        return $type ? $query->where('types', 'like', '%'.$type.'%') : $query;
    }

    /**
     * Not yet converted or discarded.
     */
    public function isAvailable(): bool
    {
        return in_array($this->lead_status, ['novo', 'reaproveitavel']);
    }

    public function isConverted(): bool
    {
        return $this->lead_status === 'convertido';
    }

    public function markAsUsed(string $status, ?string $reason = null): bool
    {
        $this->lead_status = $status;
        $this->used_at = now();
        $this->used_by = auth()->id();
        $this->used_reason = $reason;

        return $this->save();
    }
}
