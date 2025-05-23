<?php

declare(strict_types=1);

namespace Buddy\Repman\Validator;

use Buddy\Repman\Query\User\OrganizationQuery;
use Buddy\Repman\Service\Organization\AliasGenerator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UniqueOrganizationValidator extends ConstraintValidator
{
    public function __construct(private readonly AliasGenerator $aliasGenerator, private readonly OrganizationQuery $organizationQuery)
    {
    }

    /**
     * @param mixed                         $value
     * @param Constraint|UniqueOrganization $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value || !$constraint instanceof UniqueOrganization) {
            return;
        }

        if (!$this->organizationQuery->getByAlias($this->aliasGenerator->generate($value))->isEmpty()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
