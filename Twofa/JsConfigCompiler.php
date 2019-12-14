<?php namespace Ewll\UserBundle\Twofa;

use App\Entity\User;
use LogicException;

class JsConfigCompiler
{
    private $twofaHandler;
    private $actions;

    public function __construct(
        TwofaHandler $twofaHandler,
        array $actions
    ) {
        $this->twofaHandler = $twofaHandler;
        $this->actions = $actions;
    }

    public function compile(User $user): array
    {
        $actions = [];
        foreach ($this->actions as $action) {
            $actions[$action['name']] = $action['id'];
        }
        return [
            'isStoredTwofaCode' => $this->isStoredTwofaCode($user),
            'actions' => $actions,
        ];
    }

    public function isStoredTwofaCode(User $user): bool
    {
        if (!$user->hasTwofa()) {
            throw new LogicException('Expected twofa here');
        }
        $twofa = $this->twofaHandler->getTwofaServiceByTypeId($user->twofaTypeId);
        $isStoredTwofaCode = $twofa instanceof StoredKeyTwofaInterface;

        return $isStoredTwofaCode;
    }
}
