<?php

declare(strict_types=1);

namespace Buddy\Repman\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class TokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly OrganizationProvider $organizationProvider)
    {
    }

    /**
     * @codeCoverageIgnore
     */
    public function start(Request $request, ?AuthenticationException $authException = null): JsonResponse|Response
    {
        $data = [
            'message' => 'Authentication Required',
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has('PHP_AUTH_USER') && $request->headers->has('PHP_AUTH_PW');
    }

    public function authenticate(Request $request): Passport
    {
        $organization = $this->organizationProvider->loadUserByIdentifier($request->headers->get('PHP_AUTH_PW', ''));
        if ($organization->getUserIdentifier() !== $request->get('organization')) {
            throw new BadCredentialsException();
        }

        return new SelfValidatingPassport(new UserBadge($organization->getUserIdentifier(), fn (): UserInterface => $organization));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return (new JsonResponse([
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ], Response::HTTP_FORBIDDEN))->setMaxAge(60)->setPublic();
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }
}
