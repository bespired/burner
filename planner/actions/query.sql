SELECT
   owners.handle       as ownerHandle,
   owners.email        as ownerEmail,
   owners.active       as ownerActive,
   owners.type         as ownerType,
   owners.created_at   as ownerCreatedAt,
   holidays.handle     as holidayHandle,
   holidays.name       as holidayName,
   holidays.stage      as holidayStage,
   holidays.start_date as holidayStartDate,
   holidays.end_date   as holidayEndDate,
/*
   travels.handle      as travelHandle,
   travels.type        as travelType,
   travels.name        as travelName,
   travels.leave       as travelLeave,
   travels.arrive      as travelArrive,
*/
   overnights.handle   as sleepHandle,
   overnights.type     as sleepType,
   overnights.name     as sleepName,
   overnights.checkin  as sleepCheckin,
   overnights.checkout as sleepCheckout

FROM
   Owners
JOIN
   holidays
      ON holidays.owner = owners.handle
/*
JOIN
   travels
      ON travels.holiday = holidays.handle
*/
JOIN
   overnights
      ON overnights.holiday = holidays.handle

WHERE
   owners.email = "joeri@bespired.nl";
