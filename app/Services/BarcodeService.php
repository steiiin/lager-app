<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Usage;

class BarcodeService
{

  #region Code-Generation

  private static string $PREFIX = "LC-";

  private static function concatCode(int $branch, int $id, int $size = null): string
  {
    $code = ($branch * 1000000000) + ($id * 1000) + ($size);
    return self::$PREFIX . str_pad($code, 10, "0", STR_PAD_LEFT);
  }

  #endregion

  private static int $branchCtrl = 2;
  private static int $branchItems = 1;

  #region Ctrl-Codes

    private static int $ctrlFinish = 1;
    private static int $ctrlUsages = 10000;

    public static function generateCtrlFinish()
    {
      return self::concatCode(self::$branchCtrl, self::$ctrlFinish);
    }

    // ##########################################

    public static function generateCtrlBatch(): Array
    {
      return [
        self::generateCtrlFinish() => "finish"
      ];
    }

  // #endregion

  #region Usages-Codes

    public static function generateUsage(int $id)
    {
      return self::concatCode(self::$branchCtrl, self::$ctrlUsages, $id);
    }

    // ##########################################

    public static function generateUsagesBatch(): Array
    {
      $codes = [];
      foreach (Usage::all() as $usage)
      {
        $codes[self::generateUsage($usage->id)] = [
          "id" => $usage->id,
          "name" => $usage->name,
        ];
      }
      return $codes;
    }

  // #endregion

  #region Items-Codes

    public static function generateItem(int $itemId, int $sizeId)
    {
      return self::concatCode(self::$branchItems, $itemId, $sizeId);
    }

    // ##########################################

    public static function generateItemsBatch(): Array
    {

      $items = Item::with(['sizes', 'demand'])->get();
      $codes = $items->flatMap(function ($item) {
        return $item->sizes->mapWithKeys(function ($size) use ($item) {
            return [BarcodeService::generateItem($item->id, $size->id) => [
                "id" => $item->id,
                "name" => $item->name,
                "demand" => $item->demand->name,
                "amount" => $size->amount,
                "unit" => $size->unit,
                "isdefault" => $size->is_default === 1,
            ]];
        });
      });

      return $codes->toArray();

    }

  // #endregion

  public static function generateAll(): Array
  {
    return [
      "ctrl" => self::generateCtrlBatch(),
      "usages" => self::generateUsagesBatch(),
      "items" => self::generateItemsBatch(),
    ];
  }

}
