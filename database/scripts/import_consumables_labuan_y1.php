<?php

$category = \App\Models\Category::findOrFail(1);

$location = \App\Models\Location::updateOrCreate(
    ['code' => 'LWH'],
    [
        'name' => 'Labuan Inventory',
        'type' => \App\Enums\LocationType::Yard->value,
        'parent_id' => null,
        'active' => true,
    ]
);

$items = json_decode(<<<'JSON'
[
  {"item_code":"CON-Y1-0001","description":"AIR HOSE 1 1/2\"","opening_stock":0,"uom":"ROLL","standard_cost":1200.00,"rack_no":"GNRL TOP STORE","remarks":null},
  {"item_code":"CON-Y1-0002","description":"AIR HOSE 1/2\"","opening_stock":0,"uom":"ROLL","standard_cost":105.00,"rack_no":"GNRL TOP STORE","remarks":null},
  {"item_code":"CON-Y1-0003","description":"AIR HOSE 1\"","opening_stock":0,"uom":"ROLL","standard_cost":395.00,"rack_no":"GNRL TOP STORE","remarks":null},
  {"item_code":"CON-Y1-0004","description":"AIR HOSE 2\"","opening_stock":0,"uom":"ROLL","standard_cost":1800.00,"rack_no":"GNRL TOP STORE","remarks":null},
  {"item_code":"CON-Y1-0005","description":"BAG, SELF SEAL, CLEAR PLASTIC BAG 9\" X 14\" (1 PKT/100 PCS)","opening_stock":108,"uom":"PKT","standard_cost":38.00,"rack_no":"GS-18-A","remarks":null},
  {"item_code":"CON-Y1-0006","description":"BAG, SUGAR BAG","opening_stock":0,"uom":"PC","standard_cost":0.85,"rack_no":"GS-1-A","remarks":null},
  {"item_code":"CON-Y1-0007","description":"BAG, SUGAR BAG","opening_stock":550,"uom":"PC","standard_cost":0.80,"rack_no":"GS-1-A","remarks":null},
  {"item_code":"CON-Y1-0008","description":"BAG, JUMBO BAG (POLYROPELENE BAG)","opening_stock":0,"uom":"PC","standard_cost":2.50,"rack_no":"GS-11-B","remarks":null},
  {"item_code":"CON-Y1-0009","description":"BAG, JUMBO BAG 2 TON","opening_stock":0,"uom":"PC","standard_cost":45.00,"rack_no":"FLOOR","remarks":null},
  {"item_code":"CON-Y1-0010","description":"BAG, JUMBO BAG 2 TON","opening_stock":1,"uom":"PC","standard_cost":48.00,"rack_no":"GS-21-A","remarks":null},
  {"item_code":"CON-Y1-0011","description":"BAG, EMPTY GMA GARNET BAG CAPACITY 2 TON","opening_stock":0,"uom":"PC","standard_cost":60.00,"rack_no":"GS-21-A","remarks":null},
  {"item_code":"CON-Y1-0012","description":"BAND,IT DENVER","opening_stock":0,"uom":"PC","standard_cost":10.00,"rack_no":"GS-19-B","remarks":null},
  {"item_code":"CON-Y1-0013","description":"BAND, STRAPPING BAND MS 5/8\" (15KG/ROLL)","opening_stock":4,"uom":"ROLL","standard_cost":200.00,"rack_no":"FLOOR","remarks":null},
  {"item_code":"CON-Y1-0014","description":"BAND, STRAPPING BAND MS 5/8\" (15KG/ROLL)","opening_stock":0,"uom":"ROLL","standard_cost":200.00,"rack_no":"FLOOR","remarks":null},
  {"item_code":"CON-Y1-0015","description":"BLANKET, FIRE BLANKET","opening_stock":0,"uom":"PC","standard_cost":980.00,"rack_no":"GS-22-A","remarks":null},
  {"item_code":"CON-Y1-0016","description":"BROOM, ROUGH NECK BROOM C/W LONG HANDLE","opening_stock":0,"uom":"PC","standard_cost":5.50,"rack_no":"GS-11-A","remarks":null},
  {"item_code":"CON-Y1-0017","description":"BRUSH, DOG LEG PAINT BRUSH 1\"","opening_stock":0,"uom":"PC","standard_cost":1.50,"rack_no":"GS-12-A","remarks":null},
  {"item_code":"CON-Y1-0018","description":"BRUSH, DOG LEG PAINT BRUSH 2\"","opening_stock":6,"uom":"PC","standard_cost":1.80,"rack_no":"GS-12-A","remarks":null},
  {"item_code":"CON-Y1-0019","description":"BRUSH, DOG LEG PAINT BRUSH 2\"","opening_stock":0,"uom":"PC","standard_cost":1.82,"rack_no":"GS-12-A","remarks":null},
  {"item_code":"CON-Y1-0020","description":"BRUSH, WIRE CUP BRUSH 3\"","opening_stock":0,"uom":"PC","standard_cost":15.00,"rack_no":"GS-23-B","remarks":null},
  {"item_code":"CON-Y1-0021","description":"BRUSH, WIRE CUP BRUSH 4\"","opening_stock":0,"uom":"PC","standard_cost":24.00,"rack_no":"GS-23-B","remarks":null},
  {"item_code":"CON-Y1-0022","description":"BRUSH, WIRE HAND BRUSH STRAIGHT HANDLE STAINLESS STEEL","opening_stock":0,"uom":"PC","standard_cost":5.57,"rack_no":"RACK 4","remarks":null},
  {"item_code":"CON-Y1-0023","description":"BRUSH, WIRE HAND BRUSH STRAIGHT HANDLE \"SHOE HANDLE\"","opening_stock":0,"uom":"PC","standard_cost":1.30,"rack_no":"RACK 4","remarks":null},
  {"item_code":"CON-Y1-0024","description":"BUCKLE, FOR BAND, 5/8\", MS (200PCS/BOX)","opening_stock":0,"uom":"BOX","standard_cost":19.00,"rack_no":"GS-19-A","remarks":null},
  {"item_code":"CON-Y1-0025","description":"BUCKLE, FOR BAND, 5/8\", MS (200PCS/BOX)","opening_stock":5,"uom":"BOX","standard_cost":18.00,"rack_no":"GS-19-A","remarks":null},
  {"item_code":"CON-Y1-0026","description":"CANVAS, 25\" X 25\" GREEN","opening_stock":0,"uom":"ROLL","standard_cost":390.00,"rack_no":"FLOOR","remarks":null},
  {"item_code":"CON-Y1-0027","description":"CLIP, BULLDOG CLIP, 3/4\" GALV'D CHINA","opening_stock":100,"uom":"PC","standard_cost":2.70,"rack_no":"GS-13-A","remarks":null},
  {"item_code":"CON-Y1-0028","description":"CLIP, BULLDOG CLIP, 3/4\" GALV'D CHINA, OFFER: 20MM","opening_stock":0,"uom":"PC","standard_cost":1.50,"rack_no":"GS-13-A","remarks":null},
  {"item_code":"CON-Y1-0029","description":"PIN, SAFETY PIN COTTER, 4MM, FOR SHACKLE","opening_stock":0,"uom":"PC","standard_cost":0.24,"rack_no":"GS-24-B","remarks":null},
  {"item_code":"CON-Y1-0030","description":"PIN, SAFETY PIN COTTER, 6MM, FOR SHACKLE \"OFFER: 6MM X 50MM\"","opening_stock":0,"uom":"PC","standard_cost":0.25,"rack_no":"GS-18-A","remarks":null},
  {"item_code":"CON-Y1-0031","description":"PIN, COTTER PIN, M6 X 50MM","opening_stock":250,"uom":"PC","standard_cost":0.25,"rack_no":"GS-18-1","remarks":null},
  {"item_code":"CON-Y1-0032","description":"PIN, SAFETY PIN FOR SHACKLE 3.25 TON","opening_stock":0,"uom":"PC","standard_cost":0.25,"rack_no":"GS-18-A","remarks":null},
  {"item_code":"CON-Y1-0033","description":"PIN, SAFETY PIN COTTER, 4MM, FOR SHACKLE","opening_stock":100,"uom":"PC","standard_cost":0.15,"rack_no":"GS-24-B","remarks":null},
  {"item_code":"CON-Y1-0034","description":"PIN, COTTON PIN, GALV, 4MM X 40MM","opening_stock":500,"uom":"PC","standard_cost":0.20,"rack_no":"GS-18-1","remarks":null},
  {"item_code":"CON-Y1-0035","description":"PLYWOOD, 4' X 8' X 5.2MM THK","opening_stock":0,"uom":"PC","standard_cost":38.00,"rack_no":"GS-29-A","remarks":null},
  {"item_code":"CON-Y1-0036","description":"PLYWOOD, 4' X 8' X 12MM THK","opening_stock":0,"uom":"PC","standard_cost":78.00,"rack_no":"FLOOR","remarks":null},
  {"item_code":"CON-Y1-0037","description":"PLYWOOD, 4' X 8' X 12MM THK","opening_stock":12,"uom":"PC","standard_cost":72.00,"rack_no":"FLOOR","remarks":null},
  {"item_code":"CON-Y1-0038","description":"PLYWOOD, 4\" X 8\" X 6MM, OFFER 5.2MM THK X 4' X 8'","opening_stock":0,"uom":"PC","standard_cost":30.00,"rack_no":"FLOOR","remarks":null},
  {"item_code":"CON-Y1-0039","description":"PLYWOOD, 4\" X 8\" X 3MM","opening_stock":50,"uom":"PC","standard_cost":30.00,"rack_no":"FLOOR","remarks":null},
  {"item_code":"CON-Y1-0040","description":"PLYWOOD, 4\" X 8\" X 6MM","opening_stock":0,"uom":"PC","standard_cost":40.00,"rack_no":"FLOOR","remarks":null},
  {"item_code":"CON-Y1-0041","description":"RAG, COTTON RAG (20KG/1BAG)","opening_stock":1,"uom":"BAG","standard_cost":55.00,"rack_no":"FLOOR","remarks":"RECEIVED 5 BAGS"},
  {"item_code":"CON-Y1-0042","description":"REFILL, PAINT ROLLER REFILL 4\" 'CRISTIN'  OFFER: TIGER SERIES PAINT ROLLER REFILL 4\" CHINA","opening_stock":0,"uom":"PC","standard_cost":0.80,"rack_no":"Rack","remarks":null},
  {"item_code":"CON-Y1-0043","description":"REFILL, PAINT ROLLER REFILL 4\" 'CRISTIN'","opening_stock":0,"uom":"PC","standard_cost":0.75,"rack_no":"GS-23-B","remarks":null},
  {"item_code":"CON-Y1-0044","description":"REFILL, PAINT ROLLER REFILL 4\" 'CRISTIN', ORIGIN CHINA","opening_stock":40,"uom":"PC","standard_cost":0.80,"rack_no":"GS-23-B","remarks":null},
  {"item_code":"CON-Y1-0045","description":"RESPIRATOR, REUSABLE HALF-FACE DUAL RESPIRATOR \"3M 6200\"","opening_stock":0,"uom":"SET","standard_cost":48.00,"rack_no":"GS-17-A","remarks":null},
  {"item_code":"CON-Y1-0046","description":"REMOVAL, PAINT REMOVAL (4 LITRE/TIN)","opening_stock":0,"uom":"TIN","standard_cost":48.00,"rack_no":"GS-13-A","remarks":null},
  {"item_code":"CON-Y1-0047","description":"ROPE, MANILA ROPE 1/2\" X 200MTR","opening_stock":0,"uom":"ROLL","standard_cost":137.00,"rack_no":"FLOOR","remarks":null},
  {"item_code":"CON-Y1-0048","description":"ROPE, MANILA ROPE 1/2\" X 220MTR","opening_stock":24,"uom":"ROLL","standard_cost":144.00,"rack_no":"FLOOR","remarks":null},
  {"item_code":"CON-Y1-0049","description":"ROPE, MANILA ROPE 1/2\" X 200MTR","opening_stock":0,"uom":"ROLL","standard_cost":140.00,"rack_no":"FLOOR","remarks":"RECEIVED 20 PCS"},
  {"item_code":"CON-Y1-0050","description":"ROPE, MANILA ROPE 1/4\" X 220MTR","opening_stock":0,"uom":"ROLL","standard_cost":52.25,"rack_no":"FLOOR","remarks":null},
  {"item_code":"CON-Y1-0051","description":"ROPE, MANILA ROPE 5/8\" X 200MTR","opening_stock":0,"uom":"ROLL","standard_cost":257.00,"rack_no":"FLOOR","remarks":null},
  {"item_code":"CON-Y1-0052","description":"SHEET, POLYTHENE SHEET 0.5MTR X 20KG","opening_stock":0,"uom":"ROLL","standard_cost":98.00,"rack_no":"GS-32-A","remarks":null},
  {"item_code":"CON-Y1-0053","description":"SHEET, POLYTHENE SHEET 2MTR X 30KG","opening_stock":49,"uom":"ROLL","standard_cost":210.00,"rack_no":"GS-21-B","remarks":null},
  {"item_code":"CON-Y1-0054","description":"SHEET, POLYTHENE SHEET 6FT X 30KG","opening_stock":0,"uom":"ROLL","standard_cost":160.05,"rack_no":null,"remarks":null},
  {"item_code":"CON-Y1-0055","description":"SHEET, TARPAULIN SHEET, BLUE & WHITE 6 X 100FT","opening_stock":3,"uom":"ROLL","standard_cost":62.00,"rack_no":"GS-3-A / GS-4-A","remarks":null},
  {"item_code":"CON-Y1-0056","description":"TAPE, MASKING TAPE 2\" WIDE, OFFER: 72 ROLLS/BOX","opening_stock":0,"uom":"ROLL","standard_cost":1.30,"rack_no":"GS-7-A","remarks":null},
  {"item_code":"CON-Y1-0057","description":"TAPE, MASKING TAPE 2\" WIDE","opening_stock":197,"uom":"ROLL","standard_cost":1.45,"rack_no":"GS-7-A","remarks":null},
  {"item_code":"CON-Y1-0058","description":"TAPE, MASKING TAPE 3\" WIDE, OFFER: 48 ROLLS/BOX","opening_stock":0,"uom":"ROLL","standard_cost":2.40,"rack_no":"GS-2-7-3-A","remarks":null},
  {"item_code":"CON-Y1-0059","description":"TAPE, MASKING TAPE 3\" WIDE","opening_stock":361,"uom":"ROLL","standard_cost":3.20,"rack_no":"GS-2-7-3-A","remarks":null},
  {"item_code":"CON-Y1-0060","description":"TAPE, TEFLON PIFE TAPE 1/2\"(THREAD SEAL)","opening_stock":0,"uom":"PC","standard_cost":0.40,"rack_no":"GS-23-B","remarks":null},
  {"item_code":"CON-Y1-0061","description":"TIE,CABLE TIE 8MM x 300MM","opening_stock":0,"uom":"PC","standard_cost":2.08,"rack_no":"GS-23-B","remarks":null},
  {"item_code":"CON-Y1-0062","description":"WRAPPING FILM 500MM (W) x 1.6KG (6 ROLL/BOX)","opening_stock":59,"uom":"ROLL","standard_cost":15.00,"rack_no":"GS-2-A","remarks":null},
  {"item_code":"CON-Y1-0063","description":"WRAPPING FILM 500MM (W) x 1.6KG","opening_stock":0,"uom":"ROLL","standard_cost":30.00,"rack_no":"GS-2-A","remarks":"RECEIVED 120 ROLLS"},
  {"item_code":"CON-Y1-0064","description":"WD 40 \"ANTI RUST LUBRICANT GT40\"","opening_stock":0,"uom":"CAN","standard_cost":5.40,"rack_no":"GS-17-A","remarks":null},
  {"item_code":"CON-Y1-0065","description":"WD 40 LUBRICATING OIL (ANTI-RUST)","opening_stock":14,"uom":"BTL","standard_cost":5.50,"rack_no":"GS-17-A","remarks":null},
  {"item_code":"CON-Y1-0066","description":"WHEEL, PIPE CUTTER WHEEL \"RECORD 202\"","opening_stock":0,"uom":"PC","standard_cost":48.00,"rack_no":"GS-14-A","remarks":null}
]
JSON, true);

$created = 0;
$updated = 0;

foreach ($items as $item) {
    $model = \App\Models\InventoryItem::updateOrCreate(
        ['item_code' => $item['item_code']],
        [
            'description' => $item['description'],
            'category_id' => $category->id,
            'uom' => $item['uom'],
            'default_location_id' => $location->id,
            'opening_stock' => $item['opening_stock'],
            'standard_cost' => $item['standard_cost'],
            'minimum_stock' => null,
            'rack_no' => $item['rack_no'],
            'active' => true,
            'remarks' => $item['remarks'],
        ]
    );

    if ($model->wasRecentlyCreated) {
        $created++;
    } else {
        $updated++;
    }
}

echo 'location_id='.$location->id.PHP_EOL;
echo 'created='.$created.PHP_EOL;
echo 'updated='.$updated.PHP_EOL;
echo 'total_items='.\App\Models\InventoryItem::count().PHP_EOL;
