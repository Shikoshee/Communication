<?php

include "includes/header.php";

include "includes/sidebar.php";

include "includes/navbar.php";

?>


<div class="welcome">

<h1>
Dashboard
</h1>


<p>
Welcome back, Administrator
</p>


</div>



<div class="cards">


<div class="card blue">

<i class="fas fa-folder"></i>

<h2>
125
</h2>

<p>
Total Documents
</p>

</div>



<div class="card green">

<i class="fas fa-check"></i>

<h2>
89
</h2>

<p>
Approved Documents
</p>

</div>



<div class="card orange">

<i class="fas fa-clock"></i>

<h2>
21
</h2>

<p>
Pending Approval
</p>

</div>



<div class="card red">

<i class="fas fa-share"></i>

<h2>
40
</h2>

<p>
Shared Files
</p>

</div>


</div>



<div class="charts">

<div class="chart-card">

<h3>
Department Activity
</h3>


<canvas id="departmentChart"></canvas>


</div>

</div>



<div class="table-card">


<h3>
Recent Documents
</h3>


<table>


<tr>

<th>
Document
</th>


<th>
Department
</th>


<th>
Status
</th>


</tr>



<tr>

<td>
Budget Report.pdf
</td>

<td>
Finance
</td>


<td>

<span class="badge approved">
Approved
</span>

</td>

</tr>



</table>


</div>

echo "<pre>";
print_r($_SESSION['permissions']);
echo "</pre>";
exit;

<?php

include "includes/footer.php";

?>