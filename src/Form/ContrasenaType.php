<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ContrasenaType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'Contraseña',
            'attr' => ['autocomplete' => 'new-password', 'minlength' => 6, 'maxlength' => 4096],
            'help' => 'Al menos 6 caracteres, una minúscula, una mayúscula y un símbolo especial (por ejemplo, ! o @).',
            'constraints' => [
                new Assert\NotBlank(message: 'Ingresá una contraseña.'),
                new Assert\Length(min: 6, max: 4096, minMessage: 'La contraseña debe tener al menos 6 caracteres.', maxMessage: 'La contraseña es demasiado larga.'),
                // Las Regex buscan minúscula, mayúscula y símbolo. Aceptan letras con tilde.
                new Assert\Regex(pattern: '/\p{Ll}/u', message: 'Incluí una minúscula.'),
                new Assert\Regex(pattern: '/\p{Lu}/u', message: 'Incluí una mayúscula.'),
                new Assert\Regex(pattern: '/[^\p{L}\p{N}\s]/u', message: 'Incluí un símbolo especial.'),
            ],
        ]);
    }

    public function getParent(): string
    {
        return PasswordType::class;
    }
}
