<?php

declare(strict_types=1);

namespace Buddy\Repman\Tests\Functional\Controller;

use Buddy\Repman\Tests\Functional\FunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use function Ramsey\Uuid\v4;

final class RegistrationControllerTest extends FunctionalTestCase
{
    public function testSuccessfulRegistration(): void
    {
        $this->client->request(Request::METHOD_GET, $this->urlTo('app_register'));
        $this->client->submitForm('Sign up', [
            'email' => 'test@buddy.works',
            'plainPassword[first]' => 'secret123',
            'plainPassword[second]' => 'secret123',
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirect($this->urlTo('organization_create')));

        $this->client->followRedirect();
        $this->assertStringContainsString('Your account has been created', $this->lastResponseBody());
    }

    public function testSuccessfulRegistrationWithInvitationToken(): void
    {
        $this->client->disableReboot();
        $this->client->request(Request::METHOD_GET, $this->urlTo('app_register'));
        $this->client->getRequest()->getSession()->set('organization-token', $token = v4());
        $this->client->submitForm('Sign up', [
            'email' => 'test@buddy.works',
            'plainPassword[first]' => 'secret123',
            'plainPassword[second]' => 'secret123',
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirect($this->urlTo('organization_accept_invitation', [
            'token' => $token,
        ])));
    }

    public function testEmailConfirmed(): void
    {
        $this->fixtures->createUser('test@buddy.works', 'secret', ['ROLE_USER'], $confirmToken = 'f731109f-505a-4459-b51d-b142e1046664');

        $this->client->request(Request::METHOD_GET, $this->urlTo('app_register_confirm', ['token' => $confirmToken]));
        $this->assertTrue($this->client->getResponse()->isRedirect($this->urlTo('index')));

        $this->client->followRedirects();
        $this->client->followRedirect();
        $this->assertStringContainsString('E-mail address was confirmed.', $this->lastResponseBody());
    }

    public function testInvalidToken(): void
    {
        $this->fixtures->createUser('test@buddy.works', 'secret', ['ROLE_USER'], $confirmToken = 'f731109f-505a-4459-b51d-b142e1046664');

        $this->client->request(Request::METHOD_GET, $this->urlTo('app_register_confirm', ['token' => 'ffffffff-505a-4459-b51d-b142e1046664']));
        $this->assertTrue($this->client->getResponse()->isRedirect($this->urlTo('index')));

        $this->client->followRedirects();
        $this->client->followRedirect();
        $this->assertStringContainsString('Invalid or expired e-mail confirm token', $this->lastResponseBody());
    }
}
