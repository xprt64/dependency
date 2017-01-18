<?php
////////////////////////////////////////////////////////////////////////////////
// Copyright (c) 2016 Constantin Galbenu <gica.galbenu@gmail.com>              /
////////////////////////////////////////////////////////////////////////////////

namespace Gica\Dependency;


class ConstructorAbstractFactory implements \Gica\Interfaces\Dependency\AbstractFactory
{
    /** @var array */
    private $callerInjectableConstructorArguments;

    /**
     * @var \Interop\Container\ContainerInterface
     */
    private $dependencyInjectionContainer;

    public function __construct(\Interop\Container\ContainerInterface $container)
    {
        $this->setDependencyInjectionContainer($container);
    }

    /**
     * @param \Interop\Container\ContainerInterface $dependencyInjectionContainer
     */
    private function setDependencyInjectionContainer(\Interop\Container\ContainerInterface $dependencyInjectionContainer)
    {
        $this->dependencyInjectionContainer = $dependencyInjectionContainer;
    }

    /**
     * @return \Interop\Container\ContainerInterface
     */
    private function getDependencyInjectionContainer():\Interop\Container\ContainerInterface
    {
        return $this->dependencyInjectionContainer;
    }

    /**
     * @param string $objectClass
     * @param array $callerInjectableConstructorArguments
     * @return object instanceof $objectClass
     * @throws \Exception
     */
    public function createObject($objectClass, $callerInjectableConstructorArguments = [])
    {
        $this->callerInjectableConstructorArguments = $callerInjectableConstructorArguments;

        $orderedArguments = $this->buildConstructorArguments($objectClass);

        $instance = new $objectClass(...$orderedArguments);

        return $instance;
    }

    private function buildConstructorArguments($objectClass):array
    {
        $result = [];

        $reflectionClass = new \ReflectionClass($objectClass);

        $constructor = $reflectionClass->getConstructor();

        if ($constructor) {
            foreach ($constructor->getParameters() as $i => $reflectionParameter) {
                $result[] = $this->searchParameter($reflectionParameter, $i);
            }
        }

        return $result;
    }

    private function searchParameter(\ReflectionParameter $reflectionParameter, $i)
    {
        $argument = null;

        $hintedClass = $reflectionParameter->getClass();

        if ($hintedClass) {
            $argument = $this->searchInjectableParameter($hintedClass->getName());
            if (null === $argument) {
                $argument = $this->getParameterFromContainer($hintedClass->getName());
            }
        } else {
            if ($reflectionParameter->isDefaultValueAvailable()) {
                $argument = $reflectionParameter->getDefaultValue();
            } else {
                $this->handleUnknownParameter($reflectionParameter, $i);
            }
        }
        return $argument;
    }

    private function searchInjectableParameter($hintedClassName)
    {
        $argument = null;

        foreach ($this->callerInjectableConstructorArguments as $injectableArgument) {
            if ($injectableArgument instanceof $hintedClassName) {
                return $injectableArgument;
            }
        }
        return $argument;
    }

    private function getParameterFromContainer($hintedClassName)
    {
        if ($hintedClassName === \Interop\Container\ContainerInterface::class)
            return $this->getDependencyInjectionContainer();

        return $this->getDependencyInjectionContainer()->get($hintedClassName);
    }

    private function handleUnknownParameter(\ReflectionParameter $reflectionParameter, $i)
    {
        throw new \Exception(sprintf("Parameter #%d of %s is not type-hinted and does not have default value", $i, $reflectionParameter->getDeclaringClass()->getName()));
    }
}