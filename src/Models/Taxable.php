<?php namespace Cartrabbit\Taxonomies\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Taxable
 * @package Cartrabbit\Taxonomies\Models
 */
class Taxable extends Model
{
    /**
     * @todo make this editable via config file
     * @inheritdoc
     */
    protected $table = 'j2storefour_taxables';

    /**
     * @inheritdoc
     */
    protected $fillable = [
        'taxonomy_id',
        'taxable_id',
        'taxable_type'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function taxable()
    {
        return $this->morphTo();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function taxonomy()
    {
        return $this->belongsTo(Taxonomy::class, 'taxonomy_id', 'id');
    }

}