<?php

get_header();

global $wpdb;
$booking_table = $wpdb->prefix . 'bobooking';
$tour_table = $wpdb->prefix . 'botour';

// $vehicle_array = get_vehicle(2);
// print_r($vehicle_array);
?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12">
            <div class="home-tab">
                <h4 class="display-4 mb-4">Xe đang chạy hôm nay</h4>
                <div class="statistics-details d-flex flex-row gap-3 flex-wrap">
                    <?php 
                        // get all booking where status is "Đang chạy"
                        $bookings = $wpdb->get_results("SELECT * FROM $booking_table WHERE status = 'Đang chạy'");
                        if (empty($bookings)) {
                            echo '<i>Không có xe nào đang chạy hôm nay</i>';
                        } else {
                            foreach ($bookings as $booking) {
                                // get vehicle data by vehicle id
                                $vehicles = get_vehicle($booking->bookingID);

                                foreach ($vehicles as $vehicle) {
                                    ?>
                                    <div class="card card-rounded p-3 w200">
                                        <a href="#" class="d-flex justify-content-center flex-column text-center nav-link">
                                            <i class="d-flex fit-content badge border-radius-9 btn-inverse-success"><?php echo $booking->status; ?></i>
                                            <i class="ph ph-van icon-lg p-4"></i>
                                            <?php 
                                                // if have vehicle name, show vehicle name
                                                echo isset($vehicle->name) ? '<span class="fw-bold p-2">' . $vehicle->name . '</span>' : '';
                                                echo isset($vehicle->licensePlate) ? '<span>' . $vehicle->licensePlate . '</span>' : '';
                                                echo isset($vehicle->vehicleName) ? '<span>' . $vehicle->vehicleName . '</span>' : '';

                                                // echo travelDate with format d/m/Y
                                                $travelDate = date('d/m/Y', strtotime($booking->travelDate));
                                                echo '<span class="fw-bold mt-2">' . $travelDate . '</span>';
                                            ?>
                                            <span class="fw-bold mt-2"><?php echo $booking->itinerary; ?></span>
                                        </a>
                                    </div>
                                    <?php
                                }
                            }
                        }
                    ?>
                </div>
                <h4 class="display-4 mt-4 mb-4">Xe đã xếp lịch</h4>
                <div class="statistics-details d-flex flex-row gap-3 flex-wrap">
                    <?php 
                        // get all booking where status is "Đã xếp lịch", order by travelDate
                        $bookings = $wpdb->get_results("SELECT * FROM $booking_table WHERE status = 'Đã xếp lịch' ORDER BY travelDate");
                        foreach ($bookings as $booking) {
                            // get vehicle data by vehicle id
                            $vehicles = get_vehicle($booking->bookingID);

                            foreach ($vehicles as $vehicle) {
                               
                            ?>
                            <div class="card card-rounded p-3 w200">
                                <a href="#" class="d-flex justify-content-center flex-column text-center nav-link">
                                    <i class="d-flex fit-content badge border-radius-9 btn-inverse-warning"><?php echo $booking->status; ?></i>
                                    <i class="ph ph-van icon-lg p-4"></i>
                                    <?php 
                                        // if have vehicle name, show vehicle name
                                        echo isset($vehicle->name) ? '<span class="fw-bold p-2">' . $vehicle->name . '</span>' : '';
                                        echo isset($vehicle->licensePlate) ? '<span>' . $vehicle->licensePlate . '</span>' : '';
                                        echo isset($vehicle->vehicleName) ? '<span>' . $vehicle->vehicleName . '</span>' : '';

                                        // echo travelDate with format d/m/Y
                                        $travelDate = date('d/m/Y', strtotime($booking->travelDate));
                                        echo '<span class="fw-bold mt-2">' . $travelDate . '</span>';
                                    ?>
                                    <span><?php echo $booking->itinerary; ?></span>
                                </a>
                            </div>
                            <?php
                            }
                        }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();

