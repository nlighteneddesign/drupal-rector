<?php

namespace DrupalRector\Rector\Deprecation;

use DrupalRector\Rector\ValueObject\MethodToMethodWithCheckConfiguration;
use DrupalRector\Utility\AddCommentTrait;
use PhpParser\Node;
use PHPStan\Type\ObjectType;
use Rector\Core\Contract\Rector\ConfigurableRectorInterface;
use Rector\Core\Exception\ShouldNotHappenException;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces deprecated method calls with a new method.
 *
 * What is covered:
 * - Changes the name of the method.
 *
 * Improvement opportunities:
 * - Checks the variable has a certain class.
 *
 */
class MethodToMethodWithCheckRector extends AbstractRector implements ConfigurableRectorInterface {

    use AddCommentTrait;

    /**
     * @var MethodToMethodWithCheckConfiguration[]
     */
    private array $configuration;

    public function configure(array $configuration): void {
        $this->configureNoticesAsComments($configuration);

        foreach ($configuration as $value) {
            if (!($value instanceof MethodToMethodWithCheckConfiguration)) {
                throw new \InvalidArgumentException(sprintf(
                    'Each configuration item must be an instance of "%s"',
                    MethodToMethodWithCheckConfiguration::class
                ));
            }
        }

        $this->configuration = $configuration;
    }

    /**
     * @inheritdoc
     */
    public function getNodeTypes(): array {
        return [
            Node\Stmt\Expression::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function refactor(Node $node): ?Node {
        assert($node instanceof Node\Stmt\Expression);

        if (!$node->expr instanceof Node\Expr\MethodCall && !($node->expr instanceof Node\Expr\Assign && $node->expr->expr instanceof Node\Expr\MethodCall)) {
            return NULL;
        }

        foreach ($this->configuration as $configuration) {
            if ($node->expr instanceof Node\Expr\MethodCall && $this->getName($node->expr->name) !== $configuration->getDeprecatedMethodName()) {
                continue;
            }

            if ($node->expr instanceof Node\Expr\Assign && $node->expr->expr instanceof Node\Expr\MethodCall && $this->getName($node->expr->expr->name) !== $configuration->getDeprecatedMethodName()) {
                continue;
            }

            if ($node->expr instanceof Node\Expr\MethodCall) {
                $methodNode = $this->refactorNode($node->expr, $node, $configuration);
                if (is_null($methodNode)) {
                    continue;
                }
                $node->expr = $methodNode;
            }
            elseif ($node->expr instanceof Node\Expr\Assign && $node->expr->expr instanceof Node\Expr\MethodCall) {
                $methodNode = $this->refactorNode($node->expr->expr, $node, $configuration);
                if (is_null($methodNode)) {
                    continue;
                }
                $node->expr->expr = $methodNode;
            }

            return $node;
        }

        return NULL;
    }

    public function refactorNode(Node\Expr\MethodCall $node, Node\Stmt\Expression $statement, MethodToMethodWithCheckConfiguration $configuration): ?Node\Expr\MethodCall {
        assert($node instanceof Node\Expr\MethodCall);

        $callerType = $this->nodeTypeResolver->getType($node->var);
        $expectedType = new ObjectType($configuration->getClassName());

        $isSuperOf = $expectedType->isSuperTypeOf($callerType);
        if ($isSuperOf->yes()) {
            $node->name = new Node\Identifier($configuration->getMethodName());
            return $node;
        }

        if ($isSuperOf->maybe()) {

            if ($node->var instanceof Node\Expr\Variable) {
                $node_var = $node->var->name;
                $node_var = "$$node_var";
            } else if ($node->var instanceof Node\Expr\MethodCall) {
                $node_var = $node->var->name;
                $node_var = "$node_var()";
            } else {
                throw new ShouldNotHappenException("Unexpected node type: " . get_class($node->var));
            }
            $className = $configuration->getClassName();
            $this->addDrupalRectorComment(
                $statement,
                "Please confirm that `$node_var` is an instance of `$className`. Only the method name and not the class name was checked for this replacement, so this may be a false positive."
            );
            $node->name = new Node\Identifier($configuration->getMethodName());

            return $node;
        }
        return NULL;
    }

    public function getRuleDefinition(): RuleDefinition {
        return new RuleDefinition('Fixes deprecated MetadataBag::clearCsrfTokenSeed() calls', [
            new ConfiguredCodeSample(
                <<<'CODE_BEFORE'
$metadata_bag = new \Drupal\Core\Session\MetadataBag(new \Drupal\Core\Site\Settings([]));
$metadata_bag->clearCsrfTokenSeed();
CODE_BEFORE
                ,
                <<<'CODE_AFTER'
$metadata_bag = new \Drupal\Core\Session\MetadataBag(new \Drupal\Core\Site\Settings([]));
$metadata_bag->stampNew();
CODE_AFTER
                ,
                [
                    new MethodToMethodWithCheckConfiguration(
                        'Drupal\Core\Session\MetadataBag',
                        'clearCsrfTokenSeed',
                        'stampNew'
                    ),
                ]
            ),
            new ConfiguredCodeSample(
                <<<'CODE_BEFORE'
$url = $entity->urlInfo();
CODE_BEFORE
                ,
                <<<'CODE_AFTER'
$url = $entity->toUrl();
CODE_AFTER
                ,
                [
                    new MethodToMethodWithCheckConfiguration('Drupal\Core\Entity\EntityInterface', 'urlInfo', 'toUrl'),
                ]
            ),
            new ConfiguredCodeSample(
                <<<'CODE_BEFORE'
/* @var \Drupal\node\Entity\Node $node */
$node = \Drupal::entityTypeManager()->getStorage('node')->load(123);
$entity_type = $node->getEntityType();
$entity_type->getLowercaseLabel();
CODE_BEFORE
                ,
                <<<'CODE_AFTER'
/* @var \Drupal\node\Entity\Node $node */
$node = \Drupal::entityTypeManager()->getStorage('node')->load(123);
$entity_type = $node->getEntityType();
$entity_type->getSingularLabel();
CODE_AFTER
                ,
                [
                    new MethodToMethodWithCheckConfiguration('Drupal\Core\Entity\EntityTypeInterface', 'getLowercaseLabel', 'getSingularLabel'),
                ]
            ),
        ]);
    }

}