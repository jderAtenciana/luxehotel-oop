
<?php

// Base Class demonstrating Encapsulation
class Room
{
    // Private properties with explicit types
    private string $type;
    private float $price;
    private string $description;

    public function __construct(
        string $type,
        float $price,
        string $description
    ) {
        $this->type = $type;
        $this->price = max(0.0, $price);
        $this->description = $description;
    }

    // Magic Getter
    public function __get(string $name): mixed
    {
        return match ($name) {
            'type' => $this->type,
            'price' => $this->price,
            'description' => $this->description,
            default => throw new InvalidArgumentException(
                "Property '$name' does not exist in the Room class."
            ),
        };
    }

    // Magic Setter
    public function __set(string $name, mixed $value): void
    {
        switch ($name) {
            case 'price':
                if (!is_numeric($value) || $value < 0) {
                    $this->price = 0.0;
                } else {
                    $this->price = (float) $value;
                }
                break;

            case 'type':
                if (!is_string($value)) {
                    throw new InvalidArgumentException(
                        "Room type must be a string."
                    );
                }

                $this->type = $value;
                break;

            case 'description':
                if (!is_string($value)) {
                    throw new InvalidArgumentException(
                        "Room description must be a string."
                    );
                }

                $this->description = $value;
                break;

            default:
                throw new InvalidArgumentException(
                    "Property '$name' does not exist in the Room class."
                );
        }
    }

    // Magic String Method
    public function __toString(): string
    {
        return $this->type
            . " - ₱" . number_format($this->price, 2)
            . "/night (" . $this->description . ")";
    }
}

// Inheritance: Child classes extending the Room class
class StandardRoom extends Room
{
    public function __construct(float $price)
    {
        parent::__construct(
            "Standard",
            $price,
            "Cozy room with basic amenities"
        );
    }
}

class DeluxeRoom extends Room
{
    public function __construct(float $price)
    {
        parent::__construct(
            "Deluxe",
            $price,
            "Spacious room with city view"
        );
    }
}

class SuiteRoom extends Room
{
    public function __construct(float $price)
    {
        parent::__construct(
            "Suite",
            $price,
            "Luxury suite with premium services"
        );
    }
}

?>
