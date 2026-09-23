<?php

namespace Tests\Unit;

use App\Support\AvatarPresenter;
use PHPUnit\Framework\TestCase;

class AvatarPresenterTest extends TestCase
{
    public function test_initials_from_persian_name(): void
    {
        $this->assertSame('عا', AvatarPresenter::initials('علی احمدی'));
    }

    public function test_initials_from_single_name(): void
    {
        $this->assertSame('SA', AvatarPresenter::initials('Sara'));
    }

    public function test_for_name_returns_consistent_gradient(): void
    {
        $first = AvatarPresenter::forName('رضا کریمی');
        $second = AvatarPresenter::forName('رضا کریمی');

        $this->assertSame($first['gradient'], $second['gradient']);
        $this->assertSame('رضا کریمی', $first['name']);
        $this->assertNull($first['url']);
        $this->assertFalse($first['use_agent_icon']);
    }

    public function test_agent_fallback_uses_gender_icon_instead_of_initials(): void
    {
        $male = AvatarPresenter::forName('علی محمدی', gender: 'male', agent: true);
        $female = AvatarPresenter::forName('زهرا کریمی', gender: 'female', agent: true);

        $this->assertTrue($male['use_agent_icon']);
        $this->assertSame('male', $male['gender']);
        $this->assertSame('agent-male', $male['icon']);
        $this->assertSame(AvatarPresenter::gradientClass('علی محمدی'), $male['gradient']);

        $this->assertTrue($female['use_agent_icon']);
        $this->assertSame('female', $female['gender']);
        $this->assertSame('agent-female', $female['icon']);
        $this->assertSame(AvatarPresenter::gradientClass('زهرا کریمی'), $female['gradient']);
    }

    public function test_agent_without_gender_defaults_to_male_icon(): void
    {
        $avatar = AvatarPresenter::forName('کارشناس', agent: true);

        $this->assertTrue($avatar['use_agent_icon']);
        $this->assertSame('male', $avatar['gender']);
        $this->assertSame('agent-male', $avatar['icon']);
    }

    public function test_size_classes_include_xs(): void
    {
        $sizes = AvatarPresenter::sizeClasses('xs');

        $this->assertArrayHasKey('box', $sizes);
        $this->assertArrayHasKey('icon', $sizes);
        $this->assertStringContainsString('h-7', $sizes['box']);
    }
}
