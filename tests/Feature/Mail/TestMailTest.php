<?php

namespace Tests\Feature\Mail;

use App\Mail\TestMail;
use Tests\TestCase;

class TestMailTest extends TestCase
{
    public function test_it_renders_the_markdown_body(): void
    {
        // render() resolves the markdown mail components; a misconfigured
        // mailable (e.g. view: instead of markdown:) throws "No hint path
        // defined for [mail]" here.
        $rendered = (new TestMail)->render();

        $this->assertStringContainsString('test email', strtolower($rendered));
    }

    public function test_it_has_the_expected_subject(): void
    {
        $mail = new TestMail;
        $mail->assertHasSubject('Inventorix test email');
    }
}
