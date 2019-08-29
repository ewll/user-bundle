<?php namespace Ewll\UserBundle\AccessRule;

class AccessRuleProvider
{
    private $accessRules;

    public function __construct(iterable $accessRules)
    {
        $this->accessRules = $accessRules;
    }

    public function findById(int $id): ?AccessRuleInterface
    {
        /** @var AccessRuleInterface $accessRule */
        foreach ($this->accessRules as $accessRule) {
            if ($id === $accessRule->getId()) {
                return $accessRule;
            }
        }

        return null;
    }

    public function findByName(string $name): ?AccessRuleInterface
    {
        /** @var AccessRuleInterface $accessRule */
        foreach ($this->accessRules as $accessRule) {
            if ($name === $accessRule->getName()) {
                return $accessRule;
            }
        }

        return null;
    }

    public function findByClassName(string $className): ?AccessRuleInterface
    {
        /** @var AccessRuleInterface $accessRule */
        foreach ($this->accessRules as $accessRule) {
            if ($accessRule instanceof $className) {
                return $accessRule;
            }
        }

        return null;
    }

    public function compileJsConfigViewsIndexedById(): iterable
    {
        $accessRules = [];
        /** @var AccessRuleInterface $accessRule */
        foreach ($this->accessRules as $accessRule) {
            $isList = $accessRule instanceof AccessRuleListInterface;
            $accessRuleId = $accessRule->getId();
            $accessRules[$accessRuleId] = [
                'id' => $accessRuleId,
                'isList' => $isList,
            ];
        }

        return $accessRules;
    }
}
