<?php

namespace App\Form;

use App\Entity\Rol;
use App\Entity\Usuario;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class UsuarioType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nombre', TextType::class, [
                'label' => 'Nombre',
                'constraints' => [
                    new Assert\NotBlank(message: 'Ingresá el nombre.'),
                    new Assert\Length(max: 100, maxMessage: 'El nombre no puede superar los {{ limit }} caracteres.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new Assert\NotBlank(message: 'Ingresá el email.'),
                    new Assert\Email(message: 'Ingresá un email válido.'),
                    new Assert\Length(max: 180, maxMessage: 'El email no puede superar los {{ limit }} caracteres.'),
                ],
            ])
            ->add('rol', EntityType::class, [
                'label' => 'Rol',
                'class' => Rol::class,
                'choices' => $options['roles_permitidos'],
                'choice_label' => 'nombre',
                'placeholder' => 'Seleccioná un rol',
                'invalid_message' => 'Seleccioná un rol permitido.',
                'constraints' => [new Assert\NotNull(message: 'Seleccioná el rol.')],
            ])
            ->add('fechaNacimiento', DateType::class, [
                'label' => 'Fecha de nacimiento',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'invalid_message' => 'Ingresá una fecha válida.',
                'constraints' => [
                    new Assert\NotNull(message: 'Ingresá la fecha de nacimiento.'),
                    new Assert\LessThanOrEqual(value: 'today', message: 'La fecha de nacimiento no puede ser futura.'),
                ],
            ]);

        if ($options['es_nuevo']) {
            $builder->add('password', ContrasenaType::class, [
                'label' => 'Contraseña',
                'mapped' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Usuario::class,
            'roles_permitidos' => [],
            'es_nuevo' => false,
            'csrf_token_id' => 'usuario_gestion',
        ]);
        $resolver->setAllowedTypes('roles_permitidos', 'array');
        $resolver->setAllowedTypes('es_nuevo', 'bool');
    }
}
