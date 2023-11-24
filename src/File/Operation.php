<?php

declare(strict_types=1);

namespace WsdlToPhp\PackageGenerator\File;

use WsdlToPhp\PackageGenerator\Model\Struct as StructModel;
use WsdlToPhp\PhpGenerator\Element\PhpFunctionParameter;
use WsdlToPhp\PhpGenerator\Element\PhpMethod;

final class Operation extends AbstractOperation
{
    public function getMainMethod(): PhpMethod
    {
        $phpMethod = new PhpMethod($this->getMethod()->getMethodName(), []);
        $this
            ->defineParameters($phpMethod)
            ->defineBody($phpMethod)
        ;

        return $phpMethod;
    }

    protected function defineParameters(PhpMethod $method): self
    {
        return $this->defineParametersFromArray($method)->defineParametersFromModel($method)->defineParametersFromString($method);
    }

    protected function defineParametersFromArray(PhpMethod $method): self
    {
        if ($this->isParameterTypeAnArray()) {
            $parameters = [];
            foreach ($this->getParameterTypeArrayTypes(true) as $parameterName => $parameterType) {
                $parameters[] = $this->getMethodParameter($this->getParameterName($parameterName), $parameterType);
            }
            $method->setParameters($parameters);
        }

        return $this;
    }

    protected function defineParametersFromModel(PhpMethod $method): self
    {
        if ($this->isParameterTypeAModel()) {
            if ($this->getParameterTypeModel()->getAttributes(true, true)->count() > 0) {
                $method->setParameters([
                    $this->getMethodParameter($this->getParameterName($this->getParameterTypeModel()->getPackagedName()), $this->getParameterTypeModel()->getPackagedName(true)),
                ]);
            }
        }

        return $this;
    }

    protected function defineParametersFromString(PhpMethod $method): self
    {
        if ($this->isParameterTypeAString() && !$this->isParameterTypeAModel()) {
            $method->setParameters([
                $this->getMethodParameter($this->getParameterName($this->getMethod()->getParameterType())),
            ]);
        }

        return $this;
    }

    protected function defineBody(PhpMethod $method): self
    {
        $resultVariableName = sprintf('$result%s', ucfirst($this->getMethod()->getCleanName(false)));
        $method
            ->addChild('try {')
            ->addChild($this->getParametersWrap($method))
            ->addChild($method->getIndentedString(sprintf('$this->setResult(%s = $this->getSoapClient()->%s%s));', $resultVariableName, $this->getSoapCallName(), $this->getOperationCallParameters($method)), 1))
            ->addChild('')
            ->addChild($method->getIndentedString(sprintf('return %s;', $resultVariableName), 1))
            ->addChild('} catch (SoapFault $soapFault) {')
            ->addChild($method->getIndentedString('$this->saveLastError(__METHOD__, $soapFault);', 1))
            ->addChild('')
            ->addChild($method->getIndentedString('return false;', 1))
            ->addChild('}')
        ;

        return $this;
    }

    protected function getSoapCallName(): string
    {
        return sprintf('%s(\'%s\'%s', self::SOAP_CALL_NAME, $this->getMethod()->getName(), $this->getOperationCallParametersStarting());
    }

    protected function getParametersWrap(PhpMethod $method): string
    {
        $wrappedParameters = [];
        $passedParameters = $this->getMethod()->getParameterType();
        if (!is_array($passedParameters)) {
            return '';
        }
        foreach ($method->getParameters() as $parameter) {
            if (!$parameter instanceof PhpFunctionParameter) {
                continue;
            }
            $passedParameterType = $passedParameters[$parameter->getName()] ?? null;
            $model = $this->getGenerator()->getStructByName($passedParameterType);
            if (!$model instanceof StructModel) {
                continue;
            }
            $untypedParameter = $this->getUntypedParameterName($parameter);
            $wrappedParameters[] = $method->getIndentedString(sprintf('%s = %s->wrap();', $untypedParameter, $untypedParameter), 1);
        }

        return implode(PhpMethod::BREAK_LINE_CHAR, $wrappedParameters);
    }

    protected function getOperationCallParameters(PhpMethod $method): string
    {
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            if (!$parameter instanceof PhpFunctionParameter) {
                continue;
            }

            $parameters[] = $this->getOperationCallParameterName($parameter, $method);
        }

        return sprintf('%s%s, [], [], $this->outputHeaders', implode('', $parameters), $this->isParameterTypeEmpty() ? '' : PhpMethod::BREAK_LINE_CHAR.']');
    }

    protected function getOperationCallParametersStarting(): string
    {
        return $this->isParameterTypeAnArray() ? ', [' : ($this->isParameterTypeEmpty() ? ', []' : ', [');
    }

    protected function getOperationCallParametersEnding(): string
    {
        return sprintf('%s]', PhpMethod::BREAK_LINE_CHAR);
    }

    protected function getOperationCallParameterName(PhpFunctionParameter $parameter, PhpMethod $method): string
    {
        return sprintf('%s%s', PhpMethod::BREAK_LINE_CHAR, $method->getIndentedString(sprintf('%s,', $this->getUntypedParameterName($parameter)), 1));
    }

    protected function getUntypedParameterName(PhpFunctionParameter $parameter): string
    {
        $cloneParameter = clone $parameter;
        $cloneParameter->setType(null);

        return $cloneParameter->getPhpDeclaration();
    }

}
