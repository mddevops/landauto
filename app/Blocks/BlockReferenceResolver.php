<?php

namespace App\Blocks;

/**
 * Tells the state validator which referenced public IDs exist in the Block's own Site/Page.
 * Each method receives candidate IDs and returns those that exist.
 */
interface BlockReferenceResolver
{
    /**
     * @param  list<string>  $ids
     * @return array<mixed>
     */
    public function existingAssets(array $ids): array;

    /**
     * @param  list<string>  $ids
     * @return array<mixed>
     */
    public function existingPages(array $ids): array;

    /**
     * @param  list<string>  $ids
     * @return array<mixed>
     */
    public function existingBlocks(array $ids): array;

    /**
     * @param  list<string>  $ids
     * @return array<mixed>
     */
    public function existingVehicles(array $ids): array;

    /**
     * Only active Popups of the Block's Site are usable action targets.
     *
     * @param  list<string>  $ids
     * @return array<mixed>
     */
    public function existingPopups(array $ids): array;
}
