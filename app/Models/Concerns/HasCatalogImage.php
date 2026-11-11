<?php

namespace App\Models\Concerns;

trait HasCatalogImage
{
    public function getImageSrcAttribute(): ?string
    {
        if (! $this->image_url) {
            return null;
        }
        if (filter_var($this->image_url, FILTER_VALIDATE_URL)
            && in_array(strtolower(parse_url($this->image_url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
            return $this->image_url;
        }

        return route('catalog-images.show', ['type' => $this->getTable(), 'id' => $this->id]);
    }
}
