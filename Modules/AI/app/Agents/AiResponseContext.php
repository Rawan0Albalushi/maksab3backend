<?php

namespace Modules\AI\app\Agents;

class AiResponseContext
{
    private array $products     = [];
    private array $stores       = [];
    private array $categories   = [];
    private array $cartItems    = [];
    private array $bogoOffers   = [];
    private array $bundles      = [];
    private array $happyHours   = [];
    private array $toolsInvoked = [];

    public function addProducts(array $products): void
    {
        $this->products = array_merge($this->products, $products);
    }

    public function addBogoOffers(array $offers): void
    {
        $this->bogoOffers = array_merge($this->bogoOffers, $offers);
    }

    public function addBundles(array $bundles): void
    {
        $this->bundles = array_merge($this->bundles, $bundles);
    }

    public function addHappyHours(array $happyHours): void
    {
        $this->happyHours = array_merge($this->happyHours, $happyHours);
    }

    public function addStores(array $stores): void
    {
        $this->stores = array_merge($this->stores, $stores);
    }

    public function addCategories(array $categories): void
    {
        $this->categories = array_merge($this->categories, $categories);
    }

    public function addCartItems(array $items): void
    {
        $this->cartItems = $items;
    }

    public function recordTool(string $toolName): void
    {
        if (! in_array($toolName, $this->toolsInvoked, true)) {
            $this->toolsInvoked[] = $toolName;
        }
    }

    public function getProducts(): array
    {
        return $this->products;
    }

    public function getStores(): array
    {
        return $this->stores;
    }

    public function getCategories(): array
    {
        return $this->categories;
    }

    public function getCartItems(): array
    {
        return $this->cartItems;
    }

    public function getBogoOffers(): array
    {
        return $this->bogoOffers;
    }

    public function getBundles(): array
    {
        return $this->bundles;
    }

    public function getHappyHours(): array
    {
        return $this->happyHours;
    }

    public function getToolsInvoked(): array
    {
        return $this->toolsInvoked;
    }

    public function reset(): void
    {
        $this->products     = [];
        $this->stores       = [];
        $this->categories   = [];
        $this->cartItems    = [];
        $this->bogoOffers   = [];
        $this->bundles      = [];
        $this->happyHours   = [];
        $this->toolsInvoked = [];
    }
}
