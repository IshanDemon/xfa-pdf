<?php

declare(strict_types=1);

namespace Xfa\Pdf;

/**
 * Fluent section proxy — scopes all operations to a specific XFA section.
 *
 * Usage:
 *   $xfa->scopeOfWork()->read();
 *   $xfa->scopeOfWork()->readField('changesToPractice');
 *   $xfa->scopeOfWork()->update(['field1' => 'val1', 'field2' => 'val2']);
 *   $xfa->scopeOfWork()->updateField('changesToPractice', 'New value');
 */
class Section
{
    private XfaPdfManager $manager;

    private string $sectionName;

    public function __construct(XfaPdfManager $manager, string $sectionName)
    {
        $this->manager = $manager;
        $this->sectionName = $sectionName;
    }

    /**
     * Read all fields in this section.
     *
     * @return array<string, mixed>
     */
    public function read(): array
    {
        return $this->manager->read($this->sectionName);
    }

    /**
     * Read a single field value from this section.
     */
    public function readField(string $fieldName): ?string
    {
        return $this->manager->readField($this->sectionName, $fieldName);
    }

    /**
     * Update multiple fields in this section.
     *
     * @param array<string, string> $fields ['fieldName' => 'value', ...]
     */
    public function update(array $fields): self
    {
        $this->manager->update($this->sectionName, $fields);

        return $this;
    }

    /**
     * Update a single field in this section.
     */
    public function updateField(string $fieldName, string $value): self
    {
        $this->manager->updateField($this->sectionName, $fieldName, $value);

        return $this;
    }

    /**
     * Get the underlying section name.
     */
    public function getName(): string
    {
        return $this->sectionName;
    }
}
