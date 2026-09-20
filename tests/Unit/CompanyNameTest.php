<?php

namespace Tests\Unit;

use App\Support\CompanyName;
use PHPUnit\Framework\TestCase;

class CompanyNameTest extends TestCase
{
    public function test_display_normalizes_arabic_letters_and_spaces(): void
    {
        $this->assertSame('شرکت آلفا', CompanyName::display('  شركت   آلفا  '));
        $this->assertSame('شرکت میرکو', CompanyName::display('شرکت شرکت میرکو'));
    }

    public function test_key_strips_legal_prefixes_and_hamza_variants(): void
    {
        $this->assertSame('الفا', CompanyName::key('شرکت آلفا'));
        $this->assertSame('الفا', CompanyName::key('آلفا'));
        $this->assertSame('میرکو', CompanyName::key('گروه میرکو'));
        $this->assertSame('همراه', CompanyName::key('شرکت همراه سهامی خاص'));
    }

    public function test_matches_own_organization_with_prefix_and_arabic_letters(): void
    {
        $this->assertTrue(CompanyName::isOwnOrganization('شرکت میرکو', 'میرکو'));
        $this->assertTrue(CompanyName::isOwnOrganization('ميركو', 'میرکو'));
        $this->assertTrue(CompanyName::isOwnOrganization('هلدینگ میرکو', 'شرکت میرکو'));
        $this->assertFalse(CompanyName::isOwnOrganization('آلفا', 'میرکو'));
        $this->assertFalse(CompanyName::isOwnOrganization('فروشگاه نوران', 'نور'));
    }

    public function test_prefer_display_uses_correct_persian_spelling(): void
    {
        $this->assertSame('شرکت آلفا', CompanyName::preferDisplay('شركت آلفا', 'شرکت آلفا'));
    }
}
