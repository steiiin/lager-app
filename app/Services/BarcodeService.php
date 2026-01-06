<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Usage;

class BarcodeService
{

  #region Code-Generation

    private static string $PREFIX = "LC-";

    private static function concatCode(int $branch, int $id, int $size = 0): string
    {
      $code = ($branch * 1000000000) + ($id * 1000) + ($size);
      return self::$PREFIX . str_pad($code, 10, "0", STR_PAD_LEFT);
    }

  #endregion

  private static int $branchCtrl = 2;
  private static int $branchItems = 1;

  #region Ctrl-Codes

    private static int $ctrlFinish = 1;
    private static int $ctrlExpired = 10;

    private static int $ctrlUsages = 10000;

    public static function generateCtrlFinish()
    {
      return self::concatCode(self::$branchCtrl, self::$ctrlFinish);
    }
    public static function generateCtrlExpired()
    {
      return self::concatCode(self::$branchCtrl, self::$ctrlExpired);
    }

    // ##########################################

    public static function generateCtrlBatch(): Array
    {
      return [
        [ "name" => "finish", "code" => self::generateCtrlFinish() ],
        [ "name" => "expired", "code" => self::generateCtrlExpired() ],
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
        $codes[] = [
          "name" => $usage->name,
          "code" => self::generateUsage($usage->id),
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

      $items = Item::all();
      $codes = $items->map(function ($item) {
        return $item->sizes->mapWithKeys(function ($size) use ($item) {
          return [
            "name" => $item->name,
            "code" => BarcodeService::generateItem($item->id, $size->id),
            "demand" => $item->demand->name,
            "amount" => $size->amount,
            "unit" => $size->unit,
            "is_default" => $size->is_default === 1,
          ];
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
