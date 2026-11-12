<?php

namespace Tests\Feature;

use Tests\TestCase;

class HtaccessConfigTest extends TestCase
{
    public function test_public_storage_is_rewritten_before_private_source_directories_are_denied(): void
    {
        $rules = file_get_contents(base_path('.htaccess'));

        $this->assertIsString($rules);
        $publicStorageRule = 'RewriteRule ^storage(?:/(.*))?$ public/storage/$1 [L,NE]';
        $sourceDenyRule = 'RewriteRule ^(?:app|bootstrap|config|database|docs|resources|routes|storage|tests|vendor)(?:/|$) - [F,L,NC]';
        $publicStoragePosition = strpos($rules, $publicStorageRule);
        $sourceDenyPosition = strpos($rules, $sourceDenyRule);

        $this->assertNotFalse($publicStoragePosition);
        $this->assertNotFalse($sourceDenyPosition);
        $this->assertLessThan($sourceDenyPosition, $publicStoragePosition);
        $this->assertStringContainsString('<FilesMatch "^\\.">', $rules);
        $this->assertStringContainsString('Header always set X-Content-Type-Options "nosniff"', $rules);
    }
}
