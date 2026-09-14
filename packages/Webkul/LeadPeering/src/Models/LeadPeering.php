<?php

namespace Webkul\LeadPeering\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\Lead\Models\LeadProxy;
use Webkul\LeadPeering\Casts\SafeJsonCast;
use Webkul\LeadPeering\Contracts\LeadPeering as LeadPeeringContract;
use Webkul\Tenant\Traits\BelongsToTenant;
use Webkul\User\Models\UserProxy;

class LeadPeering extends Model implements LeadPeeringContract
{
    use BelongsToTenant;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lead_peering_prospects';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',

        // PeeringDB identity — org/net/fac, see PeeringDbService.
        'peeringdb_id',
        'peeringdb_type',
        'name',
        'aka',
        'website',
        'asn',
        'info_type',
        'info_traffic',
        'info_scope',
        'policy_general',
        'notes',
        'social_media',

        // Location. Populated for 'org'/'fac' — PeeringDB's 'net' object
        // carries no geography of its own (confirmed live: net?country=
        // is silently ignored by the API, unlike org/fac).
        'country',
        'state',
        'city',
        'address1',
        'address2',
        'zipcode',
        'latitude',
        'longitude',

        // PeeringDB scale signals — how present this org/network/facility
        // is elsewhere, a real qualifying signal for a data-center-focused
        // pitch (e.g. "224 facilities, 251 exchanges" for a Tier 1 network).
        'net_count',
        'fac_count',
        'ix_count',
        'region_continent',
        'sales_email',
        'sales_phone',
        'tech_email',
        'tech_phone',

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
        'social_media' => SafeJsonCast::class,
        'emails_found' => SafeJsonCast::class,
        'socios' => SafeJsonCast::class,
        'email_verified' => 'boolean',
        'net_count' => 'integer',
        'fac_count' => 'integer',
        'ix_count' => 'integer',
        'used_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'enrichment_score' => 'integer',
        'enriched_at' => 'datetime',
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

    /**
     * A stable "type:id" identity across the three PeeringDB object types —
     * ids are only unique *within* a type (org #2 and net #2 are unrelated
     * records), so this is what the search preview and import endpoints key
     * selections by, analogous to LeadGreen's business_id.
     */
    public function getPeeringdbKeyAttribute(): string
    {
        return "{$this->peeringdb_type}:{$this->peeringdb_id}";
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

    public function scopeFilterByCountry($query, $country)
    {
        return $country ? $query->where('country', $country) : $query;
    }

    public function scopeFilterByStatus($query, $status)
    {
        return $status ? $query->where('lead_status', $status) : $query;
    }

    /**
     * Filter by PeeringDB object type ('org'/'net'/'fac') — distinct from
     * LeadGreen's scopeFilterByType(), which matches inside a json array of
     * free-text business categories. This is a single, fixed-vocabulary column.
     */
    public function scopeFilterByPeeringdbType($query, $type)
    {
        return $type ? $query->where('peeringdb_type', $type) : $query;
    }

    public function scopeFilterByMinNetCount($query, $count)
    {
        return $count ? $query->where('net_count', '>=', $count) : $query;
    }

    public function scopeHasWebsite($query, $hasWebsite)
    {
        return $hasWebsite !== null
            ? $query->whereNotNull('website')->where('website', '!=', '')
            : $query;
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
