<?php namespace Cartrabbit\Taxonomies\Models;

use Cartrabbit\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Term
 * @package Cartrabbit\Taxonomies\Models
 */
class Term extends Model
{
	use Sluggable;
	use SoftDeletes;

	/**
	 * @inheritdoc
	 */
	protected $table = 'terms';

	/**
     * @todo make this editable via config file
	 * @inheritdoc
	 */
	protected $fillable = [
		'name',
		'slug',
	];

	/**
	 * @inheritdoc
	 */
	protected $dates = [
	    'deleted_at'
    ];

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    public function sluggable()
    {
        return [
            'slug' => [
                'source' => 'name'
            ]
        ];
    }

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\MorphMany
	 */
	public function taxable() {
		return $this->morphMany(Taxable::class, 'taxable');
	}

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function taxonomies() {
		return $this->hasMany(Taxonomy::class);
	}

    public function meta()
    {
        return $this->hasMany(TermMeta::class, 'term_id');
    }

	/**
	 * Get display name.
	 *
	 * @param  string $locale
	 * @param  int    $limit
	 * @return mixed
	 */
	public function getDisplayName($locale = '', $limit = 0)
    {
		$locale = $locale ?: app()->getLocale();

		switch ($locale) {
			case 'en' :
			default :
				$name = $this->name;
				break;
/*
			case 'de' :
				$name = $this->name_de;
				break;

			case 'it' :
				$name = $this->name_it;
				break;
*/
		}

		return $limit > 0 ? str_limit($name, $limit) : $name;
	}

	/**
	 * Get route parameters.
	 *
     * @param  string  $taxonomy
	 * @return mixed
	 */
	public function getRouteParameters($taxonomy)
    {
        $taxonomy = Taxonomy::taxonomy($taxonomy)
                            ->term($this->name)
                            ->with('parent')
                            ->first();

        $parameters = $this->getParentSlugs($taxonomy);

        array_push($parameters, $taxonomy->taxonomy);

        return array_reverse($parameters);
	}

    /**
     * Get slugs of parent terms.
     *
     * @param  Taxonomy  $taxonomy
     * @param  array     $parameters
     * @return array
     */
    function getParentSlugs(Taxonomy $taxonomy, $parameters = [])
    {
        array_push($parameters, $taxonomy->term->slug);

        if (($parents = $taxonomy->parent()) && ($parent = $parents->first()))
            return $this->getParentSlugs($parent, $parameters);

        return $parameters;
    }
}