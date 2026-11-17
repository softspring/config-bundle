<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Form;

use JsonException;
use Softspring\Bundle\ConfigBundle\Entity\ConfigOverride;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;

final class ConfigValueForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $definition = $options['definition'];
        $type = $definition['type'];
        $fieldType = match ($type) {
            'text', 'json' => TextareaType::class,
            'boolean' => CheckboxType::class,
            'integer' => IntegerType::class,
            'float' => NumberType::class,
            default => TextType::class,
        };
        $field = ['label' => $definition['label'], 'help' => $definition['help'], 'required' => false];
        if (in_array($type, ['string', 'text'], true)) {
            $field['empty_data'] = '';
            $field['constraints'] = [new Length(max: $definition['max_length'])];
        }
        if (in_array($type, ['integer', 'float'], true) && !$definition['nullable']) {
            $field['constraints'] = [new NotNull()];
        }
        if ('float' === $type) {
            $field['input'] = 'float';
        }
        $builder->add('value', $fieldType, $field);
        if ('json' === $type) {
            $builder->get('value')->addModelTransformer(new CallbackTransformer(
                static fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                static function (?string $value): mixed {
                    try {
                        return json_decode($value ?? '', true, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $exception) {
                        throw new TransformationFailedException('Invalid JSON.', $exception->getCode(), previous: $exception);
                    }
                },
            ));
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ConfigOverride::class, 'translation_domain' => 'sfs_config', 'csrf_protection' => true]);
        $resolver->setRequired('definition');
        $resolver->setAllowedTypes('definition', 'array');
    }
}
