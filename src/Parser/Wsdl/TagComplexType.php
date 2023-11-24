<?php

declare(strict_types=1);

namespace WsdlToPhp\PackageGenerator\Parser\Wsdl;

use WsdlToPhp\DomHandler\AbstractAttributeHandler;
use WsdlToPhp\PackageGenerator\Model\AbstractModel;
use WsdlToPhp\PackageGenerator\Model\Wsdl;
use WsdlToPhp\WsdlHandler\AbstractDocument;
use WsdlToPhp\WsdlHandler\Tag\AbstractTag;
use WsdlToPhp\WsdlHandler\Tag\TagComplexType as ComplexType;
use WsdlToPhp\WsdlHandler\Wsdl as WsdlDocument;

final class TagComplexType extends AbstractTagParser
{
    public function parseComplexType(ComplexType $complexType): void
    {
        $this->parseTagAttributes($complexType);

        /** @var AbstractTag $schema */
        $schema = $complexType->getSuitableParent(false, [AbstractDocument::TAG_SCHEMA], AbstractTag::MAX_DEEP, true);
        if ($schema) {
            $model = $this->getModel($complexType);
            if ($model) {
                /** @var AbstractAttributeHandler $attribute */
                foreach ($schema->getAttributes() as $attribute) {
                    if ($attribute->getName() == 'targetNamespace') {
                        $model->addMeta(AbstractModel::META_SCHEMA_TARGET_NAMESPACE, $attribute->getValue(true));
                    }
                }
            }
        }
    }

    protected function parseWsdl(Wsdl $wsdl): void
    {
        foreach ($this->getTags() as $tag) {
            $this->parseComplexType($tag);
        }
    }

    protected function parsingTag(): string
    {
        return WsdlDocument::TAG_COMPLEX_TYPE;
    }
}
