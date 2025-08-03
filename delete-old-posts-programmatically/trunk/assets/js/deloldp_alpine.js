function deloldp_Start() {
    return {
        deloldpDays: '',
        checkDays(days) {
            if( days <= 7 ) {
                var answer = confirm("Are you shure you want to delete Posts older than " + days + " days?")
                if ( !answer ) document.getElementById("deloldpDays").value = ''
            }
        },
        confirmSkipTrash() {
            if(document.getElementById("skiptrash").checked) {
                var answer = confirm("Are you sure you want to permanently delete the posts? This will result in the complete deletion of the posts without the possibility to recover them.")
                if ( !answer ) document.getElementById("skiptrash").checked = false
            }
        },
        confirmForceDelete() {
            if(document.getElementById("forcedeleteattachedimg").checked) {
                var answer = confirm("Are you sure you want to force-delete the media attached to posts? This will result in the deletion of the media attached to posts, even if it is used by another post.")
                if ( !answer ) document.getElementById("forcedeleteattachedimg").checked = false
            }
        },
        calculateDaysInMonths(daysInMonth){ 
            var daysToMonth = daysInMonth * 30
            document.getElementById("daysInXMonths").innerHTML = daysToMonth
            var calcDays = document.getElementById("deloldp-post-months").value;
            if( calcDays > 0 ) document.getElementById("deloldpDays").value = calcDays * 30;
        },
        copyDays() {
            var calcDays = document.getElementById("deloldp-post-months").value;
            if( calcDays > 0 ) document.getElementById("deloldpDays").value = calcDays * 30;
        },
        dismiss() {
            // Store the info dismiss status
            localStorage.setItem("infostatus", "dismissed")
            document.getElementById("pluginInfo").className = 'hide'
        },
        onstart(){
            // get the status for info message
            var infoStatus = localStorage.getItem("infostatus")
            if( infoStatus == 'dismissed' ) document.getElementById("pluginInfo").classList.add("hide")
        }
    }
    
}

/**
 * datepicker
 */
const MONTH_NAMES = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
];
const MONTH_SHORT_NAMES = [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec",
];
const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

function app() {
    return {
        showDatepicker: false,
        datepickerValue: "",
        selectedDate: calculateDatefromDays(),
        dateFormat: "DD-MM-YYYY",
        month: "",
        year: "",
        no_of_days: [],
        blankdays: [],
        initDate() {
            let today;
            if (this.selectedDate) {
                today = new Date(Date.parse(this.selectedDate));
            } else {
                today = new Date();
            }
            this.month = today.getMonth();
            this.year = today.getFullYear();
            this.datepickerValue = this.formatDateForDisplay(
                today
            );
        },
        formatDateForDisplay(date) {
            let formattedDay = DAYS[date.getDay()];
            let formattedDate = ("0" + date.getDate()).slice(
                -2
            ); // appends 0 (zero) in single digit date
            let formattedMonth = MONTH_NAMES[date.getMonth()];
            let formattedMonthShortName =
                MONTH_SHORT_NAMES[date.getMonth()];
            let formattedMonthInNumber = (
                "0" +
                (parseInt(date.getMonth()) + 1)
            ).slice(-2);
            let formattedYear = date.getFullYear();
            if (this.dateFormat === "DD-MM-YYYY") {
                return `${formattedDate}-${formattedMonthInNumber}-${formattedYear}`; // 02-04-2021
            }
            if (this.dateFormat === "YYYY-MM-DD") {
                return `${formattedYear}-${formattedMonthInNumber}-${formattedDate}`; // 2021-04-02
            }
            if (this.dateFormat === "D d M, Y") {
                return `${formattedDay} ${formattedDate} ${formattedMonthShortName} ${formattedYear}`; // Tue 02 Mar 2021
            }
            return `${formattedDay} ${formattedDate} ${formattedMonth} ${formattedYear}`;
        },
        isSelectedDate(date) {
            const d = new Date(this.year, this.month, date);
            return this.datepickerValue ===
                this.formatDateForDisplay(d) ?
                true :
                false;
        },
        isToday(date) {
            const today = new Date();
            const d = new Date(this.year, this.month, date);
            return today.toDateString() === d.toDateString() ?
                true :
                false;
        },
        getDateValue(date) {
            let selectedDate = new Date(
                this.year,
                this.month,
                date
            );
            this.datepickerValue = this.formatDateForDisplay(
                selectedDate
            );
            calculateDateinDays(selectedDate);
            // this.$refs.date.value = selectedDate.getFullYear() + "-" + ('0' + formattedMonthInNumber).slice(-2) + "-" + ('0' + selectedDate.getDate()).slice(-2);
            this.isSelectedDate(date);
            this.showDatepicker = false;
        },
        getNoOfDays() {
            let daysInMonth = new Date(
                this.year,
                this.month + 1,
                0
            ).getDate();
            // find where to start calendar day of week
            let dayOfWeek = new Date(
                this.year,
                this.month
            ).getDay();
            let blankdaysArray = [];
            for (var i = 1; i <= dayOfWeek; i++) {
                blankdaysArray.push(i);
            }
            let daysArray = [];
            for (var i = 1; i <= daysInMonth; i++) {
                daysArray.push(i);
            }
            this.blankdays = blankdaysArray;
            this.no_of_days = daysArray;
        }
    };
}

function calculateDateinDays(selectedDate){
    const today = new Date();
    const diffTime = Math.abs(today - selectedDate);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    document.getElementById("deloldpDays").value = (diffDays - 1);
}

function calculateDatefromDays(){
    selectedDays = document.getElementById("deloldpDays").value;
    var d = new Date();
    d.setDate(d.getDate() - selectedDays);
    setDate = d.toISOString().slice(0, 10);
    if(selectedDays != ''){
        return setDate;
    } else return "";
}