<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class RegistroType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['autocomplete' => 'email', 'maxlength' => 180],
                'constraints' => [
                    new Assert\NotBlank(message: 'Ingresá un email.'),
                    new Assert\Email(mode: 'strict', message: 'Ingresá un email con un dominio válido.'),
                    new Assert\Length(max: 180, maxMessage: 'El email no puede superar los 180 caracteres.'),
                ],
            ])
            ->add('password', ContrasenaType::class);
    }
}
