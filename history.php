<?php
/* 
Template Name: Booking history
*/
get_header();

global $wpdb;
$table_name = $wpdb->prefix . 'bobooking';

?>
<div class="content-wrapper">
    <div class="row">
        <div class="col-sm-12">
            <div class="home-tab">
                <div class="d-sm-flex align-items-center justify-content-between border-bottom">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active p-3 ps-0" id="home-tab" data-bs-toggle="tab" href="#overview"
                                role="tab" aria-controls="overview" aria-selected="true">Xe đang chạy</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link p-3" id="profile-tab" data-bs-toggle="tab" href="#carrunning" role="tab"
                                aria-selected="false">Lịch sử xe chạy</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link p-3" id="profile-tab" data-bs-toggle="tab" href="#tourhistory" role="tab"
                                aria-selected="false">Lịch sử tour</a>
                        </li>
                    </ul>
                </div>
                <div class="tab-content tab-content-basic">
                    <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="statistics-details d-flex flex-row gap-3 flex-wrap">
                                    <?php 
                                        // get all booking data where status is 'Đang chạy' or 'Đã xếp lịch' and order by travelDate
                                        $bookings = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'Đang chạy' OR status = 'Đã xếp lịch' ORDER BY travelDate");
                                        foreach ($bookings as $booking) {
                                            // set color for badge, if status is 'Đang chạy' then color is success, else is warning
                                            $color = ($booking->status == 'Đang chạy') ? 'btn-inverse-info' : 'btn-inverse-warning';
                                            $background = ($booking->status == 'Đang chạy') ? 'bluesky-bg' : '';
                                            
                                            // get vehicle data by booking id
                                            $vehicles = get_vehicle($booking->bookingID);
                                            foreach ($vehicles as $vehicle) {
                                            ?>
                                                <div class="card card-rounded p-3 w200 <?php echo $background; ?>">
                                                    <a href="#" class="d-flex justify-content-center flex-column text-center nav-link">
                                                        <i class="d-flex fit-content badge border-radius-9 <?php echo $color; ?>"><?php echo $booking->status; ?></i>
                                                        <i class="ph ph-van icon-lg p-4"></i>
                                                        <span class="fw-bold p-2"><?php echo $vehicle->name; ?></span>
                                                        <span><?php echo $vehicle->licensePlate; ?></span>
                                                        <span><?php echo $vehicle->vehicleName; ?></span>
                                                        <?php 
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
                    <div class="tab-pane fade" id="carrunning" role="tabpanel" aria-labelledby="carrunning">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="statistics-details d-flex flex-row gap-3 flex-wrap">
                                    <?php 
                                        // get all booking data where status is 'Đã hoàn thành' and order by travelDate
                                        $bookings = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'Đã hoàn thành' ORDER BY travelDate");
                                        foreach ($bookings as $booking) {
                                            // get vehicle data by booking id
                                            $vehicles = get_vehicle($booking->bookingID);
                                            foreach ($vehicles as $vehicle) {
                                            ?>
                                                <div class="card card-rounded p-3 w200">
                                                    <a href="#" class="d-flex justify-content-center flex-column text-center nav-link">
                                                        <i class="d-flex fit-content badge border-radius-9 btn-inverse-success"><?php echo $booking->status; ?></i>
                                                        <i class="ph ph-van icon-lg p-4"></i>
                                                        <span class="fw-bold p-2"><?php echo $vehicle->name; ?></span>
                                                        <span><?php echo $vehicle->licensePlate; ?></span>
                                                        <span><?php echo $vehicle->vehicleName; ?></span>
                                                        <?php 
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
                    <div class="tab-pane fade" id="tourhistory" role="tabpanel" aria-labelledby="overview">
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="statistics-details d-flex flex-row gap-3 flex-wrap">
                                    <?php 
                                        // get all tour data where status is 'Đã hoàn thành' and order by travelDate
                                        $tour_table = $wpdb->prefix . 'botour';
                                        $tours = $wpdb->get_results("SELECT * FROM $tour_table WHERE status = 'Đã hoàn thành' ORDER BY travelDate");
                                        foreach ($tours as $tour) {
                                            ?>
                                            <div class="card card-rounded p-3 w200">
                                                <a href="<?php echo home_url('/tours/?tourid=' . $tour->tourID); ?>" class="d-flex justify-content-center flex-column text-center nav-link">
                                                    <i class="d-flex fit-content badge border-radius-9 btn-inverse-success"><?php echo $tour->status; ?></i>
                                                    <i class="ph ph-island icon-lg p-4"></i>
                                                    <div class="p-2 d-flex flex-column">
                                                        <span class="fw-bold">
                                                            <?php
                                                            // get partner name by partner id, show partner name
                                                            $partner_table = $wpdb->prefix . 'bopartner';
                                                            $partner = $wpdb->get_row("SELECT * FROM $partner_table WHERE partnerID = $tour->partnerID");
                                                            echo $partner->name;
                                                            ?>
                                                        </span>
                                                        <span class="fw-bold">
                                                            <?php
                                                            // if date is not empty, then show date 
                                                            // format date to show only date
                                                            if ($tour->travelDate) {
                                                                echo date('d/m/Y', strtotime($tour->travelDate));
                                                            }
                                                            ?>
                                                        </span>
                                                    </div>
                                                    <span><?php echo $tour->itinerary; ?></span>
                                                    <?php 
                                                    // show number of guests and car request, if empty, show nothing
                                                    if ($tour->guests) {
                                                        echo '<span>' . $tour->guests . ' khách</span>';
                                                    } else {
                                                        echo '<span>--</span>';
                                                    }
                                                    ?>
                                                    <span>Loại <?php echo $tour->carRequest; ?></span>
                                                </a>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();

