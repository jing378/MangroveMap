<?php

namespace Tests\Feature;

use Tests\TestCase;

class TermsBackLinkTest extends TestCase
{
    public function test_sign_up_opens_the_terms_marked_as_coming_from_sign_up(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('href="'.route('terms', ['from' => 'register']).'"', false);
    }

    private function backLink(string $html): string
    {
        preg_match('/<a class="back-link".*?<\/a>/s', $html, $match);

        return $match[0] ?? '';
    }

    public function test_terms_opened_from_sign_up_go_back_to_sign_up(): void
    {
        // Even when the session's "previous page" is the landing page.
        $response = $this->withSession(['_previous' => ['url' => url('/')]])
            ->get(route('terms', ['from' => 'register']))
            ->assertOk();

        $link = $this->backLink($response->getContent());
        $this->assertStringContainsString('Back to sign up', $link);
        $this->assertStringContainsString('href="'.route('register').'"', $link);
        $this->assertStringContainsString('data-back-to-register', $link);
    }

    public function test_terms_opened_elsewhere_keep_the_normal_back_link(): void
    {
        $response = $this->get(route('terms'))->assertOk();

        $link = $this->backLink($response->getContent());
        $this->assertStringNotContainsString('data-back-to-register', $link);
        $this->assertStringNotContainsString('Back to sign up', $link);
    }
}
